<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Timezone;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Tests\Support\CreatesTimezoneTables;
use Tests\TestCase;

/**
 * The timezone lookups must not trip TenantQueryListener (one error log per job otherwise).
 */
class TimezoneQueryListenerTest extends TestCase
{
    use CreatesTimezoneTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTimezoneTables();
        $this->useAppTimezone('UTC');

        config()->set('multi-tenant.query_listener.enabled', true);
        config()->set('multi-tenant.tables', ['branches' => 'App\\Models\\Branch']);
        Log::spy();
    }

    protected function tearDown(): void
    {
        $this->tearDownTimezoneTables();

        parent::tearDown();
    }

    public function testLookupsWithTenantContextDoNotLogMissingTenantFilter(): void
    {
        app(TenantContext::class)->setTenantId(1);

        $this->assertSame('Europe/London', TenantTimezone::for(1, $this->cairoBranch));
        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, $this->cairoBranchNoZone));
        // null tenant + branch: select by uuid is accepted as a primary-key lookup
        $this->assertSame('UTC', TenantTimezone::for(null, Str::uuid()->toString()));

        Log::shouldNotHaveReceived('error');
    }

    public function testStoredZoneScanDoesNotLogMissingTenantFilter(): void
    {
        app(TenantContext::class)->setTenantId(1);
        DB::connection('testing')->table('branches')->insert([
            'uuid' => Str::uuid()->toString(), 'tenant_id' => null, 'timezone' => 'Asia/Dubai', 'deleted_at' => null,
        ]);

        $this->artisan('tenant:check-tzdata', ['--tenants' => true])->assertFailed();

        Log::shouldNotHaveReceived('error');
        // the scan restores the tenant it found
        $this->assertSame(1, app(TenantContext::class)->getTenantId());
    }
}
