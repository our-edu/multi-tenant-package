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

    public function testMiddlewareSkipsResolutionForExcludedRoutes(): void
    {
        config(['multi-tenant.excluded_routes' => ['health', 'login']]);

        $request = Request::create('/health', 'GET');
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

    public function testMiddlewareSkipsResolutionForWildcardExcludedRoutes(): void
    {
        config(['multi-tenant.excluded_routes' => ['password/*']]);

        $request = Request::create('/password/reset', 'GET');

        $next = function ($req) {
            return 'response';
        };

        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testMiddlewareResolvesForNonExcludedRoutes(): void
    {
        config(['multi-tenant.excluded_routes' => ['health']]);

        $request = Request::create('/dashboard', 'GET');

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

    public function testMatchesPatternWithExactMatch(): void
    {
        $middleware = new class () extends TenantMiddleware {
            public function exposedMatchesPattern(string $value, string $pattern): bool
            {
                return $this->matchesPattern($value, $pattern);
            }
        };

        $this->assertTrue($middleware->exposedMatchesPattern('health', 'health'));
        $this->assertFalse($middleware->exposedMatchesPattern('dashboard', 'health'));
    }

    public function testMatchesPatternWithWildcard(): void
    {
        $middleware = new class () extends TenantMiddleware {
            public function exposedMatchesPattern(string $value, string $pattern): bool
            {
                return $this->matchesPattern($value, $pattern);
            }
        };

        $this->assertTrue($middleware->exposedMatchesPattern('password/reset', 'password/*'));
        $this->assertTrue($middleware->exposedMatchesPattern('password/forgot', 'password/*'));
        $this->assertFalse($middleware->exposedMatchesPattern('user/password', 'password/*'));
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
}
