<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mockery;
use Oured\MultiTenant\Middleware\TenantMiddleware;
use Oured\MultiTenant\Tenancy\TenantContext;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    private TenantMiddleware $middleware;
    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Mockery::mock(TenantContext::class);
        $this->app->bind(TenantContext::class, fn () => $this->context);

        $this->middleware = new TenantMiddleware();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
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

        // Verify getTenant is called to trigger lazy loading
        $this->context
            ->shouldReceive('getTenant')
            ->once();

        $this->middleware->handle($request, $next);

        $this->context->shouldHaveReceived('getTenant')->once();
    }
}

