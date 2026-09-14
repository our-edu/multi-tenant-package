<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Traits;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * SerializesDatesAsIso
 *
 * Serializes every date attribute as ISO-8601 with an offset
 * (`2026-09-13T07:00:00+00:00`) in `toArray()` / `toJson()`, so the wire
 * always carries an unambiguous instant regardless of the service's
 * `app.timezone`.
 *
 * Add it to the service's BaseModel:
 *   use SerializesDatesAsIso;
 *
 * Trap: Laravel routes plain `date` / `immutable_date` casts through
 * `serializeDate()` too, so a date-only column would come out as
 * `2026-09-13T00:00:00+03:00`. Date-only columns (birthdates, academic-year
 * bounds) must use the custom-format cast, which bypasses serialization:
 *   protected $casts = ['birthdate' => 'date:Y-m-d'];
 */
trait SerializesDatesAsIso
{
    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->toIso8601String();
    }
}
