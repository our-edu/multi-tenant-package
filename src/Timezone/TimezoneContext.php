<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Timezone;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ouredu\MultiTenant\Tenancy\CurrentSession;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Throwable;

/**
 * TimezoneContext
 *
 * Per-request / per-job cache of the current tenant timezone. Bound as a
 * scoped instance so Octane requests and queue jobs each get a fresh one.
 *
 * Resolution order for {@see current()}:
 *
 *  1. The `timezone` attribute on the user session (the JWT claim minted by
 *     IAM). Skipped in console (jobs, cron) exactly like the session-based
 *     tenant resolver, so a queue worker never touches the claims client.
 *  2. The current tenant from {@see TenantContext} (set by jobs/listeners via
 *     `setTenantId()` / `SetsTenantFromPayload`) plus the session's branch,
 *     looked up in the `branches` / `tenants` tables ({@see for()}).
 *  3. `multi-tenant.timezone.default`, then `app.timezone`, then `UTC`.
 *
 * Every value is validated against the IANA list before it is used; an
 * invalid stored value (an offset such as `+03:00`, or an alias such as
 * `Etc/GMT-2`) falls through to the next step rather than being returned.
 */
class TimezoneContext
{
    private ?string $timezone = null;

    private bool $resolved = false;

    /**
     * Guard against re-entrant resolution (a session helper may itself
     * touch the tenant context, which may resolve models that serialize dates).
     */
    private bool $resolving = false;

    /**
     * Memoized database lookups for the lifetime of this instance,
     * keyed by "{tenantId}:{branchUuid}".
     *
     * @var array<string, string>
     */
    private array $lookups = [];

    public function __construct(
        private readonly Application $app
    ) {
    }

    /**
     * The timezone of the current tenant / branch (IANA name).
     */
    public function current(): string
    {
        if ($this->resolving) {
            return $this->defaultTimezone();
        }

        if (! $this->resolved) {
            $this->resolve();
        }

        return (string) $this->timezone;
    }

    /**
     * Manually set the current timezone (tests, CLI, jobs). Null clears it.
     *
     * @throws InvalidArgumentException When the value is not an IANA timezone name
     */
    public function set(?string $timezone): void
    {
        if ($timezone === null) {
            $this->clear();

            return;
        }

        if (! TenantTimezone::isValid($timezone)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid IANA timezone (expected e.g. Africa/Cairo).',
                $timezone
            ));
        }

        $this->timezone = $timezone;
        $this->resolved = true;
        $this->resolving = false;
    }

    /**
     * Forget the resolved timezone and the lookup memo.
     */
    public function clear(): void
    {
        $this->timezone = null;
        $this->resolved = false;
        $this->resolving = false;
        $this->lookups = [];
    }

    /**
     * Run a callback with a specific current timezone, restoring the previous state afterwards.
     *
     * @template TReturn
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    public function runInTimezone(string $timezone, callable $callback): mixed
    {
        $previousTimezone = $this->timezone;
        $previousResolved = $this->resolved;

        try {
            $this->set($timezone);

            return $callback();
        } finally {
            $this->timezone = $previousTimezone;
            $this->resolved = $previousResolved;
        }
    }

    /**
     * Resolve the timezone for a tenant and optional branch from the shared database.
     *
     * Branch zone wins when the branch exists (for that tenant, not soft-deleted)
     * and has a valid zone; otherwise the tenant zone; otherwise the default.
     * `*` (all branches) and null both mean "use the tenant zone".
     */
    public function for(?int $tenantId, ?string $branchUuid = null): string
    {
        $key = ($tenantId ?? '') . ':' . ($branchUuid ?? '');

        if (! isset($this->lookups[$key])) {
            $this->lookups[$key] = $this->lookup($tenantId, $branchUuid);
        }

        return $this->lookups[$key];
    }

    /**
     * The configured fallback zone: `multi-tenant.timezone.default`, else `app.timezone`, else UTC.
     */
    public function defaultTimezone(): string
    {
        $configured = config('multi-tenant.timezone.default');

        if (TenantTimezone::isValid($configured)) {
            return $configured;
        }

        $appTimezone = config('app.timezone');

        return TenantTimezone::isValid($appTimezone) ? $appTimezone : 'UTC';
    }

    /**
     * The zone naive database values are stored in.
     *
     * Laravel sets PHP's default zone from `app.timezone` at boot and its own
     * `datetime` cast reads naive values in that default zone, so this reads
     * the same source to stay byte-for-byte compatible with the built-in cast.
     * It is `Asia/Riyadh` before a service flips to UTC and `UTC` after; the
     * helpers are correct in both states because they always convert through it.
     */
    public function storageTimezone(): string
    {
        $timezone = date_default_timezone_get();

        return TenantTimezone::isValid($timezone) ? $timezone : 'UTC';
    }

    /**
     * Perform lazy resolution.
     */
    private function resolve(): void
    {
        $this->resolving = true;

        try {
            $this->timezone = $this->fromSession()
                ?? $this->fromTenantContext()
                ?? $this->defaultTimezone();
            $this->resolved = true;
        } finally {
            $this->resolving = false;
        }
    }

    /**
     * Step 1: the `timezone` claim carried by the user session.
     */
    private function fromSession(): ?string
    {
        $session = CurrentSession::get($this->app);

        if ($session === null) {
            return null;
        }

        $attribute = CurrentSession::timezoneAttribute();

        try {
            $timezone = $session->{$attribute} ?? null;
        } catch (Throwable) {
            return null;
        }

        return TenantTimezone::isValid($timezone) ? $timezone : null;
    }

    /**
     * Step 2: the tenant known to TenantContext (jobs, listeners, header) plus the session branch.
     */
    private function fromTenantContext(): ?string
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = $this->app->make(TenantContext::class);
        $tenantId = $tenantContext->getTenantId();

        if ($tenantId === null) {
            return null;
        }

        $branchUuid = null;
        $session = CurrentSession::get($this->app);

        if ($session !== null) {
            $attribute = CurrentSession::branchAttribute();

            try {
                $branchUuid = $session->{$attribute} ?? null;
            } catch (Throwable) {
                $branchUuid = null;
            }
        }

        return $this->for($tenantId, is_string($branchUuid) ? $branchUuid : null);
    }

    /**
     * Uncached database lookup: branch zone → tenant zone → default.
     */
    private function lookup(?int $tenantId, ?string $branchUuid): string
    {
        $column = (string) config('multi-tenant.timezone.column', 'timezone');
        $timezone = null;

        if ($branchUuid !== null && $branchUuid !== TenantTimezone::ALL_BRANCHES && Str::isUuid($branchUuid)) {
            $timezone = DB::table((string) config('multi-tenant.timezone.branches_table', 'branches'))
                ->where('uuid', $branchUuid)
                ->when($tenantId !== null, fn ($query) => $query->where(
                    (string) config('multi-tenant.tenant_column', 'tenant_id'),
                    $tenantId
                ))
                ->whereNull('deleted_at')
                ->value($column);
        }

        if (! TenantTimezone::isValid($timezone) && $tenantId !== null) {
            $timezone = DB::table((string) config('multi-tenant.timezone.tenants_table', 'tenants'))
                ->where('id', $tenantId)
                ->value($column);
        }

        return TenantTimezone::isValid($timezone) ? $timezone : $this->defaultTimezone();
    }
}
