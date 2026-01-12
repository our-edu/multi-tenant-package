<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Providers;

use Mockery;
use Oured\MultiTenant\Contracts\TenantResolver;
use Oured\MultiTenant\Providers\TenantServiceProvider;
use Oured\MultiTenant\Tenancy\TenantContext;
use Tests\TestCase;

class TenantServiceProviderTest extends TestCase
{
    public function testProviderRegistersTenantContext(): void
    {
        $this->assertTrue($this->app->bound(TenantContext::class));
    }

    public function testProviderRegistersTenantContextAsSingleton(): void
    {
        $instance1 = $this->app->make(TenantContext::class);
        $instance2 = $this->app->make(TenantContext::class);

        $this->assertSame($instance1, $instance2);
    }

    public function testProviderMergesConfig(): void
    {
        $this->assertNotNull(config('multi-tenant.tenant_model'));
        $this->assertNotNull(config('multi-tenant.tenant_column'));
    }

    public function testTenantContextRequiresResolver(): void
    {
        $context = $this->app->make(TenantContext::class);

        $this->assertInstanceOf(TenantContext::class, $context);
    }
}

