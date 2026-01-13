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

    public function testMiddlewareResolvesTenantonHandle(): void
    {
        $request = Mockery::mock(Request::class);
        $next = function ($req) {
            return 'response';
        };

        $this->context
            ->shouldReceive('getTenant')
            ->once()
            ->andReturn(null);

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }

    public function testMiddlewareCallsNextMiddleware(): void
    {
        $request = Mockery::mock(Request::class);
        $called = false;

        $next = function ($req) use (&$called) {
            $called = true;

            return 'next_response';
        };

        $this->context
            ->shouldReceive('getTenant')
            ->andReturn(null);

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
            ->shouldReceive('getTenant')
            ->once()
            ->andReturnNull();

        $response = $this->middleware->handle($request, $next);

        $this->assertEquals('response', $response);
    }
}
