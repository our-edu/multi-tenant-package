<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Ouredu\MultiTenant\Timezone\TenantTimezone;

/**
 * UtcDateTime
 *
 * Cast for *instant* columns (`created_at`-like moments in time, stored as
 * `timestamp without time zone` whose value is in `app.timezone`).
 *
 * Reading behaves exactly like Laravel's `datetime` cast (a Carbon in the
 * storage zone), so swapping is safe. Writing accepts anything
 * {@see TenantTimezone::parse()} accepts: values with an explicit offset are
 * honoured, naive values are interpreted in the current tenant zone, and the
 * result is stored in the storage zone.
 *
 * Do NOT use it for wall-clock columns (timetable `from`/`to`, `scheduled_time`,
 * birthdates, academic-year bounds). Those are never converted.
 *
 * Usage:
 *   protected $casts = ['starts_at' => UtcDateTime::class];
 */
class UtcDateTime implements CastsAttributes
{
    /**
     * Always rebuild from the stored value so a Carbon assigned in another zone
     * is read back normalized to the storage zone (Eloquent would otherwise
     * hand back the cached object you assigned).
     */
    public bool $withoutObjectCaching = true;

    /**
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->setTimezone(TenantTimezone::storage());
        }

        return Carbon::parse((string) $value, TenantTimezone::storage());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return TenantTimezone::parse($value)?->format($model->getDateFormat());
    }
}
