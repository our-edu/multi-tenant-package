<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Timezone;

use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;

/**
 * TenantTimezone
 *
 * Static entry point for tenant timezone handling. All state lives in the
 * scoped {@see TimezoneContext}; this class only adds ergonomics.
 *
 * Rules (see the tenant-timezone rollout guide):
 *  - Instants are stored in `app.timezone` (UTC once a service has flipped).
 *  - Zones are IANA names (`Africa/Cairo`), never offsets.
 *  - The wire format for instants is ISO-8601 with an offset. Naive input is
 *    interpreted in the tenant zone as a safety net, not as the contract.
 *  - Wall-clock values (timetable slots, `HH:mm`, `Y-m-d`) are never converted.
 */
final class TenantTimezone
{
    /**
     * The `branch` claim value meaning "all branches" (use the tenant zone).
     */
    public const ALL_BRANCHES = '*';

    /**
     * The timezone of the current request's tenant / branch.
     *
     * In console (jobs, cron) there is no session; the zone comes from the
     * tenant set on TenantContext, else the default. Cron code that iterates
     * tenants must call {@see for()} explicitly.
     */
    public static function current(): string
    {
        return self::context()->current();
    }

    /**
     * The timezone of a specific tenant and optional branch (database lookup, memoized per request/job).
     */
    public static function for(?int $tenantId, ?string $branchUuid = null): string
    {
        return self::context()->for($tenantId, $branchUuid);
    }

    /**
     * Override the current timezone for the rest of the request/job (null clears the override).
     */
    public static function set(?string $timezone): void
    {
        self::context()->set($timezone);
    }

    /**
     * Run a callback with a specific current timezone.
     *
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    public static function runIn(string $timezone, callable $callback): mixed
    {
        return self::context()->runInTimezone($timezone, $callback);
    }

    /**
     * The zone naive database values are stored in (`app.timezone`, which Laravel
     * applies as PHP's default zone at boot).
     */
    public static function storage(): string
    {
        return self::context()->storageTimezone();
    }

    /**
     * Whether the value is an IANA timezone identifier.
     *
     * Deliberately uses the default identifier list, so fixed-offset aliases
     * (`Etc/GMT-2`), raw offsets (`+03:00`) and bare city names are rejected.
     */
    public static function isValid(mixed $timezone): bool
    {
        return is_string($timezone)
            && $timezone !== ''
            && in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Parse an incoming instant and return it in the storage zone.
     *
     * - null / '' → null
     * - DateTimeInterface → as is
     * - int → unix timestamp
     * - string with an explicit offset or `Z` → that instant
     * - naive string (`Y-m-d H:i[:s]`, ISO without offset, `Y-m-d`) → interpreted in
     *   `$timezone` (default: the current tenant zone). A bare date is midnight there.
     *
     * @throws \Carbon\Exceptions\InvalidFormatException When the string cannot be parsed
     */
    public static function parse(mixed $value, ?string $timezone = null): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $date = Carbon::instance($value);
        } elseif (is_int($value)) {
            $date = Carbon::createFromTimestamp($value, 'UTC');
        } else {
            $date = Carbon::parse(trim((string) $value), $timezone ?? self::current());
        }

        return $date->setTimezone(self::storage());
    }

    private static function context(): TimezoneContext
    {
        return app(TimezoneContext::class);
    }
}
