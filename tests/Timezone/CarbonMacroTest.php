<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Timezone;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Tests\TestCase;

class CarbonMacroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useAppTimezone('UTC');
        TenantTimezone::set('Africa/Cairo');
    }

    public function testMacroIsRegisteredOnBothCarbonClasses(): void
    {
        $this->assertTrue(Carbon::hasMacro('inTenantTz'));
        $this->assertTrue(CarbonImmutable::hasMacro('inTenantTz'));
    }

    public function testInTenantTzReturnsACopyInTheCurrentTenantZoneWithTheSameInstant(): void
    {
        $utc = Carbon::parse('2026-07-01 07:00:00', 'UTC');

        $local = $utc->inTenantTz();

        $this->assertSame('Africa/Cairo', $local->getTimezone()->getName());
        $this->assertSame('2026-07-01 10:00:00', $local->toDateTimeString());
        $this->assertSame($utc->getTimestamp(), $local->getTimestamp());
        $this->assertNotSame($utc, $local);
        $this->assertSame('UTC', $utc->getTimezone()->getName(), 'receiver must not be mutated');
    }

    public function testInTenantTzAcceptsAnExplicitZone(): void
    {
        $utc = CarbonImmutable::parse('2026-01-15 07:00:00', 'UTC');

        $this->assertSame('2026-01-15 10:00:00', $utc->inTenantTz('Asia/Riyadh')->toDateTimeString());
        $this->assertSame('2026-01-15 09:00:00', $utc->inTenantTz('Africa/Cairo')->toDateTimeString());
        $this->assertSame('2026-01-15 09:00:00', $utc->inTenantTz()->format('Y-m-d H:i:s'));
    }
}
