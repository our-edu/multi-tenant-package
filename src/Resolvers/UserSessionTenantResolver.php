<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Resolvers;

use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Tenancy\CurrentSession;

/**
 * UserSessionTenantResolver
 *
 * Resolves the current tenant ID from a shared UserSession using a
 * configurable helper function that returns a session model with tenant_id.
 *
 * Resolution flow:
 * 1. If running in console (and not unit tests) → skip
 * 2. Call configured session helper to get the session model
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
        // Null in console (except tests), without the helper, or when it throws
        $session = $this->getSessionFromHelper();

        if (! $session) {
            return null;
        }

        // Get tenant_id from session
        return $this->getTenantIdFromSession($session);
    }

    /**
     * Get session using the configured helper function.
     *
     * @return object|null The session object/model or null
     */
    protected function getSessionFromHelper(): ?object
    {
        return CurrentSession::get();
    }

    /**
     * Get the session helper function name from config.
     */
    protected function getSessionHelperName(): string
    {
        return CurrentSession::helperName();
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
        return CurrentSession::tenantAttribute();
    }
}
