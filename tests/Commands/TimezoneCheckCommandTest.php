<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ouredu\MultiTenant\Commands\TimezoneCheckCommand;
use Tests\Support\CreatesTimezoneTables;
use Tests\TestCase;

class TimezoneCheckCommandTest extends TestCase
{
    use CreatesTimezoneTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTimezoneTables();
    }

    protected function tearDown(): void
    {
        $this->tearDownTimezoneTables();

        parent::tearDown();
    }

    public function testCommandIsRegistered(): void
    {
        $this->assertInstanceOf(
            TimezoneCheckCommand::class,
            $this->app->make('Illuminate\Contracts\Console\Kernel')->all()['tenant:check-tzdata']
        );
    }

    public function testTzdataCheckPassesOnACurrentPhpBuild(): void
    {
        $this->artisan('tenant:check-tzdata')
            ->expectsOutputToContain('PHP timezone database: ' . timezone_version_get())
            ->expectsOutputToContain('OK   Africa/Cairo on 2026-07-01 is +03:00')
            ->expectsOutputToContain('OK   Africa/Cairo on 2026-01-15 is +02:00')
            ->assertSuccessful();
    }

    public function testTenantsOptionFailsOnInvalidStoredZones(): void
    {
        // Seeded data has tenant 3 = '+03:00' and one Cairo branch = 'Etc/GMT-2'
        $this->artisan('tenant:check-tzdata', ['--tenants' => true])
            ->expectsOutputToContain('FAIL 2 stored timezone(s) are not IANA names')
            ->assertFailed();
    }

    public function testTenantsOptionAlsoScansBranchesWithoutTenant(): void
    {
        DB::connection('testing')->table('tenants')->where('id', 3)->update(['timezone' => 'Africa/Cairo']);
        DB::connection('testing')->table('branches')->where('uuid', $this->cairoBranchInvalidZone)->update(['timezone' => null]);
        DB::connection('testing')->table('branches')->insert([
            'uuid' => Str::uuid()->toString(), 'tenant_id' => null, 'timezone' => '+02:00', 'deleted_at' => null,
        ]);

        $this->artisan('tenant:check-tzdata', ['--tenants' => true])
            ->expectsOutputToContain('FAIL 1 stored timezone(s) are not IANA names')
            ->assertFailed();
    }

    public function testTenantsOptionPassesWhenEveryStoredZoneIsValid(): void
    {
        DB::connection('testing')->table('tenants')->where('id', 3)->update(['timezone' => 'Africa/Cairo']);
        DB::connection('testing')->table('branches')->where('uuid', $this->cairoBranchInvalidZone)->update(['timezone' => null]);

        $this->artisan('tenant:check-tzdata', ['--tenants' => true])
            ->expectsOutputToContain('OK   3 tenant(s) checked')
            ->assertSuccessful();
    }
}
