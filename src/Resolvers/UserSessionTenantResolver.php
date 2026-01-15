<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Resolvers;

use Illuminate\Support\Facades\App;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Throwable;

/**
 * UserSessionTenantResolver
 *
 * Resolves the current tenant ID from a shared UserSession using the
 * getSession() helper function that returns a session model with tenant_id.
 *
 * Resolution flow:
 * 1. If running in console (and not unit tests) → skip
 * 2. Call getSession() helper to get the session model
 * 3. Read tenant_id column from the session model
 * 4. Return the tenant_id as integer
 */
class UserSessionTenantResolver implements TenantResolver
{
    /**
     * Resolve the current tenant ID from the session.
     */
    public function resolveTenantId(): ?int
    {
        // Skip resolution in console (except when running tests)
        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return null;
        }

        // Get session from helper function
        $session = $this->getSessionFromHelper();

        if (! $session) {
            return null;
        }

        // Get tenant_id from session
        return $this->getTenantIdFromSession($session);
    }

    /**
     * Get session using the getSession() helper function.
     *
     * @return object|null The session object/model or null
     */
    protected function getSessionFromHelper(): ?object
    {
        if (! function_exists('getSession')) {
            return null;
        }

        try {
            $session = getSession();

            return is_object($session) ? $session : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get tenant_id from the session.
     *
     * @param object $session The session object
     * @return int|null The tenant ID
     */
    protected function getTenantIdFromSession(object $session): ?int
    {
        $tenantColumn = $this->getTenantColumn();

        $tenantId = $session->{$tenantColumn} ?? null;

        return $tenantId !== null ? (int) $tenantId : null;
    }

    /**
     * Get the tenant column name on the session model.
     */
    protected function getTenantColumn(): string
    {
        return (string) (config('multi-tenant.session.tenant_column')
            ?? config('multi-tenant.tenant_column', 'tenant_id'));
    }
}
