<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Providers;

use Carbon\Carbon;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\PresenceVerifierInterface;
use Ouredu\MultiTenant\Commands\TimezoneCheckCommand;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Timezone\TimezoneContext;
use Ouredu\MultiTenant\Validation\TenantDatabasePresenceVerifier;
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

    public function testProviderRegistersTenantAwareValidationPresenceVerifier(): void
    {
        /** @var ValidationFactory $validator */
        $validator = $this->app->make('validator');

        $this->assertInstanceOf(TenantDatabasePresenceVerifier::class, $validator->getPresenceVerifier());

        $verifier = $this->app->make(PresenceVerifierInterface::class);
        $this->assertInstanceOf(TenantDatabasePresenceVerifier::class, $verifier);
    }

    public function testProviderRegistersTimezoneContextAsScoped(): void
    {
        $this->assertTrue($this->app->bound(TimezoneContext::class));

        $first = $this->app->make(TimezoneContext::class);
        $this->assertSame($first, $this->app->make(TimezoneContext::class));

        $this->app->forgetScopedInstances();
        $this->assertNotSame($first, $this->app->make(TimezoneContext::class));
    }

    public function testProviderMergesTimezoneConfig(): void
    {
        $this->assertSame('timezone', config('multi-tenant.timezone.column'));
        $this->assertSame('tenants', config('multi-tenant.timezone.tenants_table'));
        $this->assertSame('branches', config('multi-tenant.timezone.branches_table'));
        $this->assertSame('timezone', config('multi-tenant.timezone.session_attribute'));
        $this->assertSame('branch_uuid', config('multi-tenant.timezone.session_branch_attribute'));
        $this->assertNull(config('multi-tenant.timezone.default'));
    }

    public function testProviderRegistersTimezoneCommandAndMacro(): void
    {
        $this->assertInstanceOf(
            TimezoneCheckCommand::class,
            $this->app->make('Illuminate\Contracts\Console\Kernel')->all()['tenant:check-tzdata']
        );
        $this->assertTrue(Carbon::hasMacro('inTenantTz'));
    }
}
