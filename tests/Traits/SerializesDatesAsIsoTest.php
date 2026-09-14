<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Ouredu\MultiTenant\Casts\UtcDateTime;
use Ouredu\MultiTenant\Traits\SerializesDatesAsIso;
use Tests\TestCase;

class SerializesDatesAsIsoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useAppTimezone('UTC');
    }

    public function testDatetimeAttributesSerializeAsIsoWithOffset(): void
    {
        $model = new SerializesDatesAsIsoTestModel();
        $model->created_at = Carbon::parse('2026-09-13 07:00:00', 'UTC');

        $array = $model->toArray();

        $this->assertSame('2026-09-13T07:00:00+00:00', $array['created_at']);
        $this->assertStringContainsString('"created_at":"2026-09-13T07:00:00+00:00"', $model->toJson());
    }

    public function testOffsetInSerializedOutputFollowsStorageZone(): void
    {
        $this->useAppTimezone('Asia/Riyadh');

        $model = new SerializesDatesAsIsoTestModel();
        $model->created_at = Carbon::parse('2026-09-13 10:00:00', 'Asia/Riyadh');

        // Same instant as 07:00Z, expressed with the service's storage offset — services can flip
        // to UTC one at a time and the wire stays consistent.
        $this->assertSame('2026-09-13T10:00:00+03:00', $model->toArray()['created_at']);
    }

    public function testInstantColumnsNeedTheUtcDateTimeCastToKeepTheirZone(): void
    {
        $riyadhTenO = Carbon::parse('2026-09-13 10:00:00', 'Asia/Riyadh'); // = 07:00Z

        $model = new SerializesDatesAsIsoTestModel();
        $model->published_at = $riyadhTenO; // plain 'datetime' cast
        $model->starts_at = $riyadhTenO;    // UtcDateTime cast

        $array = $model->toArray();

        // Laravel's datetime cast formats the Carbon in its own zone and drops the offset,
        // then re-reads the naive string in app.timezone (UTC): the instant silently shifts by 3h.
        $this->assertSame('2026-09-13T10:00:00+00:00', $array['published_at']);
        // UtcDateTime converts to the storage zone first, so the instant is preserved.
        $this->assertSame('2026-09-13T07:00:00+00:00', $array['starts_at']);
    }

    public function testDateOnlyColumnsMustUseCustomFormatCast(): void
    {
        $model = new SerializesDatesAsIsoTestModel();
        $model->birthdate = '2026-09-13';
        $model->plain_date = '2026-09-13';

        $array = $model->toArray();

        // 'date:Y-m-d' bypasses serializeDate() → stays a wall-clock date
        $this->assertSame('2026-09-13', $array['birthdate']);
        // a plain 'date' cast goes through serializeDate() → documented trap
        $this->assertSame('2026-09-13T00:00:00+00:00', $array['plain_date']);
    }
}

class SerializesDatesAsIsoTestModel extends Model
{
    use SerializesDatesAsIso;

    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'starts_at' => UtcDateTime::class,
        'birthdate' => 'date:Y-m-d',
        'plain_date' => 'date',
    ];
}
