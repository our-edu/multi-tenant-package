<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Oured\MultiTenant\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Oured\MultiTenant\Contracts\TenantResolver;

/**
 * TenantContext
 *
 * Shared tenant context service used across the request / job / command
 * lifecycle. It relies on a TenantResolver implementation (bound in the
 * host application) to actually figure out who the current tenant is.
 *
 * Responsibilities:
 * - Cache the current tenant model for the lifetime of the request/job
 * - Provide helper methods (getTenantId, hasTenant, clear, manual set)
 */
class TenantContext
{
    private ?Model $tenant = null;

    private bool $resolved = false;

    public function __construct(
        private readonly TenantResolver $resolver
    ) {
    }

    /**
     * Get the current tenant model (or null if not resolved).
     */
    public function getTenant(): ?Model
    {
        if (! $this->resolved) {
            $this->resolve();
        }

        return $this->tenant;
    }

    /**
     * Get the current tenant ID (assumes primary key or uuid).
     */
    public function getTenantId(): ?string
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            return null;
        }

        // Prefer uuid property if it exists, otherwise primary key
        return $tenant->uuid ?? (string) $tenant->getKey();
    }

    /**
     * Manually set the tenant model (useful for tests or CLI).
     */
    public function setTenant(?Model $tenant): void
    {
        $this->tenant   = $tenant;
        $this->resolved = true;
    }

    /**
     * Clear the tenant context.
     */
    public function clear(): void
    {
        $this->tenant   = null;
        $this->resolved = false;
    }

    /**
     * Check if tenant is set.
     */
    public function hasTenant(): bool
    {
        return $this->getTenant() !== null;
    }

    /**
     * Perform lazy resolution using the configured TenantResolver.
     */
    private function resolve(): void
    {
        $this->resolved = true;
        $this->tenant   = $this->resolver->resolveTenant();
    }
}


