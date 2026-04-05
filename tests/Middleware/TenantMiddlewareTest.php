<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getName')->andReturn('dashboard');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn($route);

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
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getName')->andReturn('dashboard');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn($route);

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
        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getName')->andReturn('dashboard');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn($route);

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

    public function testMiddlewareSkipsResolutionForExcludedRoutesByName(): void
    {
        config(['multi-tenant.excluded_routes' => ['api.health', 'auth.login']]);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getName')->andReturn('api.health');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn($route);

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

    public function testMiddlewareResolvesForNonExcludedRoutes(): void
    {
        config(['multi-tenant.excluded_routes' => ['api.health']]);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getName')->andReturn('dashboard');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn($route);

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

    public function testMiddlewareResolvesWhenRouteHasNoName(): void
    {
        config(['multi-tenant.excluded_routes' => ['api.health']]);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getName')->andReturn(null);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn($route);

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

    public function testMiddlewareResolvesWhenNoRouteResolved(): void
    {
        config(['multi-tenant.excluded_routes' => ['api.health']]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn(null);

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

    public function testMiddlewareExcludesWebhookRouteByName(): void
    {
        config(['multi-tenant.excluded_routes' => ['api.ottu.gateway.webhook']]);

        $route = Mockery::mock(Route::class);
        $route->shouldReceive('getName')->andReturn('api.ottu.gateway.webhook');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isMethod')->with('OPTIONS')->andReturn(false);
        $request->shouldReceive('route')->andReturn($route);

        $next = function ($req) {
            return 'response';
        };

        // TenantContext should NOT be called for excluded routes
        $this->context->shouldNotReceive('getTenantId');

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }
}
