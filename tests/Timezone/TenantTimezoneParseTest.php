<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Timezone;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Tests\TestCase;

/**
 * TenantTimezone::parse() — incoming instants, returned in the storage zone.
 */
class TenantTimezoneParseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useAppTimezone('UTC');
        TenantTimezone::set('Africa/Cairo');
    }

    public function testNaiveInputIsInterpretedInTenantZone(): void
    {
        // Cairo summer (+03:00)
        $this->assertSame('2026-07-01 07:00:00', TenantTimezone::parse('2026-07-01 10:00')->toDateTimeString());
        $this->assertSame('2026-07-01T07:00:00+00:00', TenantTimezone::parse('2026-07-01T10:00:00')->toIso8601String());

        // Cairo winter (+02:00)
        $this->assertSame('2026-01-15 08:00:00', TenantTimezone::parse('2026-01-15 10:00:00')->toDateTimeString());
    }

    public function testExplicitOffsetIsHonoured(): void
    {
        $this->assertSame('2026-07-01 10:00:00', TenantTimezone::parse('2026-07-01T10:00:00+00:00')->toDateTimeString());
        $this->assertSame('2026-07-01 10:00:00', TenantTimezone::parse('2026-07-01T10:00:00Z')->toDateTimeString());
        $this->assertSame('2026-07-01 07:00:00', TenantTimezone::parse('2026-07-01T10:00:00+03:00')->toDateTimeString());
    }

    public function testBareDateIsMidnightInTenantZone(): void
    {
        $this->assertSame('2026-06-30 21:00:00', TenantTimezone::parse('2026-07-01')->toDateTimeString());
    }

    public function testResultIsInStorageZone(): void
    {
        $this->useAppTimezone('Asia/Riyadh');

        $parsed = TenantTimezone::parse('2026-07-01 10:00');

        $this->assertSame('Asia/Riyadh', $parsed->getTimezone()->getName());
        $this->assertSame('2026-07-01 10:00:00', $parsed->toDateTimeString());
        $this->assertSame('2026-07-01T10:00:00+03:00', $parsed->toIso8601String());
    }

    public function testExplicitTimezoneArgumentOverridesCurrent(): void
    {
        $this->assertSame('2026-07-01 10:00:00', TenantTimezone::parse('2026-07-01 10:00', 'UTC')->toDateTimeString());
        $this->assertSame('2026-07-01 07:00:00', TenantTimezone::parse('2026-07-01 10:00', 'Asia/Riyadh')->toDateTimeString());
    }

    public function testNullAndEmptyReturnNull(): void
    {
        $this->assertNull(TenantTimezone::parse(null));
        $this->assertNull(TenantTimezone::parse(''));
    }

    public function testDateTimeInstancesAndTimestamps(): void
    {
        $immutable = new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('Africa/Cairo'));
        $this->assertSame('2026-07-01 07:00:00', TenantTimezone::parse($immutable)->toDateTimeString());

        $carbon = CarbonImmutable::parse('2026-07-01 10:00:00', 'Asia/Riyadh');
        $this->assertSame('2026-07-01 07:00:00', TenantTimezone::parse($carbon)->toDateTimeString());
        $this->assertInstanceOf(Carbon::class, TenantTimezone::parse($carbon));

        $this->assertSame('2026-07-01 07:00:00', TenantTimezone::parse(1782889200)->toDateTimeString());
    }
}
