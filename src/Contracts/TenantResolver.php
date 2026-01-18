<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Contracts;

/**
 * TenantResolver
 *
 * Each service using this package should bind an implementation of this
 * interface that knows how to resolve the current tenant ID for that service.
 *
 * Examples:
 * - Resolve tenant_id from UserSession (session helper)
 * - Resolve tenant id from request domain / subdomain
 * - Resolve tenant_id from CLI arguments (for commands)
 */
interface TenantResolver
{
    /**
     * Resolve the current tenant ID.
     *
     * @return int|null The tenant ID or null if not resolved
     */
    public function resolveTenantId(): ?int;
}
