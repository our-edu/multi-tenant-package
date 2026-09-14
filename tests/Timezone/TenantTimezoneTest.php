<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Timezone;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Ouredu\MultiTenant\Timezone\TimezoneContext;
use Tests\Support\CreatesTimezoneTables;
use Tests\TestCase;

/**
 * TenantTimezone::for() — database resolution (branch → tenant → default).
 */
class TenantTimezoneTest extends TestCase
{
    use CreatesTimezoneTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTimezoneTables();
        $this->useAppTimezone('UTC');
    }

    protected function tearDown(): void
    {
        $this->tearDownTimezoneTables();

        parent::tearDown();
    }

    public function testBranchZoneWinsOverTenantZone(): void
    {
        $this->assertSame('Europe/London', TenantTimezone::for(1, $this->cairoBranch));
    }

    public function testBranchWithoutZoneFallsBackToTenantZone(): void
    {
        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, $this->cairoBranchNoZone));
    }

    public function testAllBranchesMarkerAndNullUseTenantZone(): void
    {
        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, TenantTimezone::ALL_BRANCHES));
        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, null));
        $this->assertSame('Asia/Riyadh', TenantTimezone::for(2));
    }

    public function testBranchOfAnotherTenantIsIgnored(): void
    {
        // Riyadh's branch queried under the Cairo tenant → Cairo tenant zone
        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, $this->riyadhBranch));
    }

    public function testSoftDeletedBranchIsIgnored(): void
    {
        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, $this->cairoBranchDeleted));
    }

    public function testInvalidStoredBranchZoneFallsThroughToTenant(): void
    {
        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, $this->cairoBranchInvalidZone));
    }

    public function testInvalidStoredTenantZoneFallsThroughToDefault(): void
    {
        $this->assertSame('UTC', TenantTimezone::for(3));

        $this->useAppTimezone('Asia/Riyadh');
        $this->app->forgetScopedInstances();

        $this->assertSame('Asia/Riyadh', TenantTimezone::for(3));
    }

    public function testUnknownOrNullTenantUsesDefault(): void
    {
        $this->useAppTimezone('Asia/Riyadh');

        $this->assertSame('Asia/Riyadh', TenantTimezone::for(999));
        $this->assertSame('Asia/Riyadh', TenantTimezone::for(null));
        $this->assertSame('Asia/Riyadh', TenantTimezone::for(null, Str::uuid()->toString()));
    }

    public function testNonUuidBranchValueSkipsBranchLookup(): void
    {
        DB::connection('testing')->enableQueryLog();

        $this->assertSame('Africa/Cairo', TenantTimezone::for(1, 'not-a-uuid'));

        $this->assertCount(1, DB::connection('testing')->getQueryLog());
    }

    public function testConfiguredDefaultBeatsAppTimezone(): void
    {
        config()->set('multi-tenant.timezone.default', 'Africa/Cairo');
        $this->useAppTimezone('Asia/Riyadh');

        $this->assertSame('Africa/Cairo', TenantTimezone::for(null));
    }

    public function testLookupsAreMemoizedPerContext(): void
    {
        DB::connection('testing')->enableQueryLog();

        TenantTimezone::for(1, $this->cairoBranch);
        TenantTimezone::for(1, $this->cairoBranch);
        TenantTimezone::for(1, $this->cairoBranch);

        $this->assertCount(1, DB::connection('testing')->getQueryLog());

        app(TimezoneContext::class)->clear();
        TenantTimezone::for(1, $this->cairoBranch);

        $this->assertCount(2, DB::connection('testing')->getQueryLog());
    }

    public function testContextIsScopedNotSingleton(): void
    {
        $first = app(TimezoneContext::class);
        $this->assertSame($first, app(TimezoneContext::class));

        $this->app->forgetScopedInstances();

        $this->assertNotSame($first, app(TimezoneContext::class));
    }
}
