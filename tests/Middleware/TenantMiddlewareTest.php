<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Middleware;

use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Ouredu\MultiTenant\Exceptions\TenantNotResolvedException;
use Ouredu\MultiTenant\Middleware\TenantMiddleware;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    private TenantMiddleware $middleware;

    private TenantContext|MockInterface $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Mockery::mock(TenantContext::class);
        $this->app->instance(TenantContext::class, $this->context);

        $this->middleware = new TenantMiddleware();
    }

    public function testMiddlewareThrowsExceptionWhenTenantNotResolved(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('api/v1/ar/dashboard');

        $next = function ($req) {
            return 'response';
        };

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn(null);

        $this->expectException(TenantNotResolvedException::class);

        $this->middleware->handle($request, $next);
    }

    public function testMiddlewareCallsNextMiddlewareWhenTenantResolved(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('api/v1/ar/dashboard');

        $called = false;

        $next = function ($req) use (&$called) {
            $called = true;

            return 'next_response';
        };

        $this->context
            ->shouldReceive('getTenantId')
            ->andReturn(1);

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals('next_response', $response);
    }

    public function testMiddlewareLazyLoadsTenant(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('api/v1/ar/dashboard');

        $next = function ($req) {
            return 'response';
        };

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn(1);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testMiddlewareSkipsResolutionForOptionsPreflightRequests(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(true);
        $called = false;

        $next = function ($req) use (&$called) {
            $called = true;

            return 'cors_response';
        };

        // TenantContext should NOT be called for OPTIONS requests
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals('cors_response', $response);
    }

    public function testMiddlewareSkipsResolutionForApiExcludedPathsWithWildcard(): void
    {
        config(['multi-tenant.excluded_routes' => ['api/*/*/users']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('api/v1/ar/users');

        $called = false;

        $next = function ($req) use (&$called) {
            $called = true;

            return 'response';
        };

        // TenantContext should NOT be called for excluded routes
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals('response', $response);
    }

    public function testMiddlewareSkipsResolutionForApiPathsWithDifferentVersionsAndLangs(): void
    {
        config(['multi-tenant.excluded_routes' => ['api/*/*/users']]);

        // Test different versions and languages
        $testPaths = [
            'api/v1/ar/users',
            'api/v2/en/users',
            'api/v3/fr/users',
        ];

        foreach ($testPaths as $path) {
            $request = Mockery::mock(Request::class);
            $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
            $request->shouldReceive('path')->andReturn($path);

            $called = false;

            $next = function ($req) use (&$called) {
                $called = true;

                return 'response';
            };

            // TenantContext should NOT be called for excluded routes
            $this->context->shouldNotReceive('getTenantId');

            $response = $this->middleware->handle($request, $next);

            $this->assertTrue($called, "Path $path should be excluded");
            $this->assertEquals('response', $response);
        }
    }

    public function testMiddlewareSkipsResolutionForWebExcludedPaths(): void
    {
        config(['multi-tenant.excluded_routes' => ['users', 'health']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('users');

        $called = false;

        $next = function ($req) use (&$called) {
            $called = true;

            return 'response';
        };

        // TenantContext should NOT be called for excluded routes
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertTrue($called);
        $this->assertEquals('response', $response);
    }

    public function testMiddlewareResolvesForNonExcludedPaths(): void
    {
        config(['multi-tenant.excluded_routes' => ['api/*/*/health']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('api/v1/ar/dashboard');

        $next = function ($req) {
            return 'response';
        };

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn(1);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testGetExcludedRoutesDefaultsToEmptyArray(): void
    {
        $middleware = new class () extends TenantMiddleware {
            public function exposedGetExcludedRoutes(): array
            {
                return $this->getExcludedRoutes();
            }
        };

        // Clear the config to test default behavior
        config(['multi-tenant.excluded_routes' => []]);

        $this->assertEquals([], $middleware->exposedGetExcludedRoutes());
    }

    public function testMiddlewareExcludesApiWebhookWithWildcard(): void
    {
        config(['multi-tenant.excluded_routes' => ['api/*/*/webhook/*']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('api/v1/ar/webhook/ottu');

        $next = function ($req) {
            return 'response';
        };

        // TenantContext should NOT be called for excluded routes
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testMiddlewareMatchesExactWebPath(): void
    {
        config(['multi-tenant.excluded_routes' => ['health']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('health');

        $next = function ($req) {
            return 'response';
        };

        // TenantContext should NOT be called for excluded routes
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testMiddlewareMatchesPathWithLeadingSlash(): void
    {
        config(['multi-tenant.excluded_routes' => ['/users']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('users');

        $next = function ($req) {
            return 'response';
        };

        // TenantContext should NOT be called for excluded routes
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testMiddlewareMatchesNestedApiPathWithWildcard(): void
    {
        config(['multi-tenant.excluded_routes' => ['api/*/*/users/*/profile']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('path')->andReturn('api/v1/ar/users/123/profile');

        $next = function ($req) {
            return 'response';
        };

        // TenantContext should NOT be called for excluded routes
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testPathMatchesPatternMethod(): void
    {
        $middleware = new class () extends TenantMiddleware {
            public function exposedPathMatchesPattern(string $path, string $pattern): bool
            {
                return $this->pathMatchesPattern($path, $pattern);
            }
        };

        // Test exact match for web routes
        $this->assertTrue($middleware->exposedPathMatchesPattern('users', 'users'));
        $this->assertTrue($middleware->exposedPathMatchesPattern('health', 'health'));

        // Test API wildcard match
        $this->assertTrue($middleware->exposedPathMatchesPattern('api/v1/ar/users', 'api/*/*/users'));
        $this->assertTrue($middleware->exposedPathMatchesPattern('api/v2/en/users', 'api/*/*/users'));
        $this->assertTrue($middleware->exposedPathMatchesPattern('api/v1/ar/webhook/ottu', 'api/*/*/webhook/*'));

        // Test non-matching
        $this->assertFalse($middleware->exposedPathMatchesPattern('api/v1/ar/dashboard', 'api/*/*/users'));
        $this->assertFalse($middleware->exposedPathMatchesPattern('api/v1/users', 'api/*/*/users')); // Missing lang segment
        $this->assertFalse($middleware->exposedPathMatchesPattern('users', 'api/*/*/users')); // Web path doesn't match API pattern
    }

    public function testMiddlewareHandlesMixedApiAndWebExclusions(): void
    {
        config(['multi-tenant.excluded_routes' => [
            'api/*/*/health',
            'api/*/*/webhook/*',
            'login',
            'register',
        ]]);

        $middleware = new class () extends TenantMiddleware {
            public function exposedPathMatchesPattern(string $path, string $pattern): bool
            {
                return $this->pathMatchesPattern($path, $pattern);
            }
        };

        // API routes should match
        $this->assertTrue($middleware->exposedPathMatchesPattern('api/v1/ar/health', 'api/*/*/health'));
        $this->assertTrue($middleware->exposedPathMatchesPattern('api/v2/en/webhook/stripe', 'api/*/*/webhook/*'));

        // Web routes should match
        $this->assertTrue($middleware->exposedPathMatchesPattern('login', 'login'));
        $this->assertTrue($middleware->exposedPathMatchesPattern('register', 'register'));

        // Non-excluded routes should not match
        $this->assertFalse($middleware->exposedPathMatchesPattern('api/v1/ar/dashboard', 'api/*/*/health'));
        $this->assertFalse($middleware->exposedPathMatchesPattern('dashboard', 'login'));
    }
}
