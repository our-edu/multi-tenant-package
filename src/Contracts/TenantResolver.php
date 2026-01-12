<?php

declare(strict_types=1);

namespace Oured\MultiTenant\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * TenantResolver
 *
 * Each service using this package should bind an implementation of this
 * interface that knows how to resolve the current tenant for that service.
 *
 * Examples:
 * - Resolve from UserSession (session header / auth user)
 * - Resolve from request domain / subdomain
 * - Resolve from CLI arguments (for commands)
 */
interface TenantResolver
{
    /**
     * Resolve the current tenant model.
     *
     * @return Model|null
     */
    public function resolveTenant(): ?Model;
}


