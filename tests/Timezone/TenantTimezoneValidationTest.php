<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Timezone;

use DateTime;
use DateTimeZone;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests: no container, no database.
 */
class TenantTimezoneValidationTest extends TestCase
{
    public function testAcceptsIanaZoneNames(): void
    {
        $this->assertTrue(TenantTimezone::isValid('Asia/Riyadh'));
        $this->assertTrue(TenantTimezone::isValid('Africa/Cairo'));
        $this->assertTrue(TenantTimezone::isValid('UTC'));
    }

    public function testRejectsOffsetsAliasesAndGarbage(): void
    {
        $this->assertFalse(TenantTimezone::isValid('+03:00'));
        // Fixed-offset alias: wrong for Cairo since Egypt reinstated DST in 2023. Must never be stored.
        $this->assertFalse(TenantTimezone::isValid('Etc/GMT-2'));
        $this->assertFalse(TenantTimezone::isValid('Cairo'));
        $this->assertFalse(TenantTimezone::isValid(''));
        $this->assertFalse(TenantTimezone::isValid(null));
        $this->assertFalse(TenantTimezone::isValid(3));
        $this->assertFalse(TenantTimezone::isValid(['Africa/Cairo']));
    }

    public function testAllBranchesMarkerIsStar(): void
    {
        $this->assertSame('*', TenantTimezone::ALL_BRANCHES);
    }

    public function testCairoObservesDstInTheShippedTzDatabase(): void
    {
        $summer = (new DateTime('2026-07-01 12:00:00', new DateTimeZone('Africa/Cairo')))->format('P');
        $winter = (new DateTime('2026-01-15 12:00:00', new DateTimeZone('Africa/Cairo')))->format('P');

        $this->assertSame('+03:00', $summer, 'tzdata is stale: Egypt reinstated DST in 2023');
        $this->assertSame('+02:00', $winter);
    }
}
