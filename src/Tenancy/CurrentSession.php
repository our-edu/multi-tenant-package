<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Tenancy;

use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * The service's user session, read through its helper (multi-tenant.session.helper),
 * and the session attributes the package reads. HasTokenClaims writes the same
 * attribute names, so the writer and the readers always agree.
 */
final class CurrentSession
{
    /**
     * The session object, or null in console (except unit tests), when the helper
     * is missing, or when it throws.
     *
     * Swallowing matters: a service's getSession() rejects a refused or unverifiable
     * IAM token by throwing. The tenant resolver then reports "tenant not resolved",
     * and the service's exception handler answers with IAM's own response
     * (token_claims()->failure()).
     *
     * @param Application|null $app the application to check for console; the current one by default
     */
    public static function get(?Application $app = null): ?object
    {
        $app ??= app();

        // Queue workers and cron have no bearer token behind them
        if ($app->runningInConsole() && ! $app->runningUnitTests()) {
            return null;
        }

        $helper = self::helperName();

        if (! function_exists($helper)) {
            return null;
        }

        try {
            $session = $helper();

            return is_object($session) ? $session : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function helperName(): string
    {
        return (string) config('multi-tenant.session.helper', 'getSession');
    }

    /**
     * The session attribute holding the tenant id.
     */
    public static function tenantAttribute(): string
    {
        return (string) (config('multi-tenant.session.tenant_column')
            ?? config('multi-tenant.tenant_column', 'tenant_id'));
    }

    /**
     * The session attribute holding the IANA timezone.
     */
    public static function timezoneAttribute(): string
    {
        return (string) config('multi-tenant.timezone.session_attribute', 'timezone');
    }

    /**
     * The session attribute holding the active branch uuid.
     */
    public static function branchAttribute(): string
    {
        return (string) config('multi-tenant.timezone.session_branch_attribute', 'branch_uuid');
    }
}
