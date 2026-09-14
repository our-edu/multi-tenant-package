<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Casts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ouredu\MultiTenant\Casts\UtcDateTime;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Ouredu\MultiTenant\Traits\SerializesDatesAsIso;
use Tests\TestCase;

class UtcDateTimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('multi-tenant.query_listener.enabled', false);
        $this->useAppTimezone('UTC');

        Schema::connection('testing')->create('events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('starts_at')->nullable();
        });

        TenantTimezone::set('Africa/Cairo');
    }

    protected function tearDown(): void
    {
        Schema::connection('testing')->dropIfExists('events');

        parent::tearDown();
    }

    public function testNaiveInputIsStoredInStorageZoneFromTenantZone(): void
    {
        $event = new UtcDateTimeTestEvent();
        $event->starts_at = '2026-07-01 10:00';
        $event->save();

        $this->assertSame('2026-07-01 07:00:00', DB::connection('testing')->table('events')->value('starts_at'));
    }

    public function testInputWithOffsetIsStoredAsThatInstant(): void
    {
        $event = new UtcDateTimeTestEvent();
        $event->starts_at = '2026-07-01T10:00:00+03:00';
        $event->save();

        $this->assertSame('2026-07-01 07:00:00', DB::connection('testing')->table('events')->value('starts_at'));
    }

    public function testStorageZoneFollowsAppTimezoneBeforeTheFlip(): void
    {
        $this->useAppTimezone('Asia/Riyadh');

        $event = new UtcDateTimeTestEvent();
        $event->starts_at = '2026-07-01T10:00:00+03:00';
        $event->save();

        $this->assertSame('2026-07-01 10:00:00', DB::connection('testing')->table('events')->value('starts_at'));

        $event->starts_at = '2026-07-01 10:00'; // naive → Cairo (+03:00 in July) → Riyadh (+03:00)
        $event->save();

        $this->assertSame('2026-07-01 10:00:00', DB::connection('testing')->table('events')->value('starts_at'));
    }

    public function testReadingReturnsCarbonInStorageZone(): void
    {
        DB::connection('testing')->table('events')->insert(['starts_at' => '2026-07-01 07:00:00']);

        $event = UtcDateTimeTestEvent::query()->first();

        $this->assertInstanceOf(Carbon::class, $event->starts_at);
        $this->assertSame('UTC', $event->starts_at->getTimezone()->getName());
        $this->assertSame('2026-07-01 07:00:00', $event->starts_at->toDateTimeString());
        $this->assertSame('2026-07-01 10:00:00', $event->starts_at->inTenantTz()->toDateTimeString());
    }

    public function testNullRoundTrips(): void
    {
        $event = new UtcDateTimeTestEvent();
        $event->starts_at = null;
        $event->save();

        $this->assertNull(UtcDateTimeTestEvent::query()->first()->starts_at);
    }

    public function testSerializationWithAndWithoutTrait(): void
    {
        DB::connection('testing')->table('events')->insert(['starts_at' => '2026-07-01 07:00:00']);

        $plain = UtcDateTimeTestEvent::query()->first()->toArray();
        $iso = UtcDateTimeTestIsoEvent::query()->first()->toArray();

        $this->assertSame('2026-07-01T07:00:00+00:00', $iso['starts_at']);
        // Laravel's default serializer is ISO too (with microseconds, in UTC) — same instant.
        $this->assertSame('2026-07-01T07:00:00.000000Z', $plain['starts_at']);
    }
}

class UtcDateTimeTestEvent extends Model
{
    protected $table = 'events';

    protected $connection = 'testing';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['starts_at' => UtcDateTime::class];
}

class UtcDateTimeTestIsoEvent extends UtcDateTimeTestEvent
{
    use SerializesDatesAsIso;
}
