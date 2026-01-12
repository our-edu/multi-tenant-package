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
     * Set the tenant by ID.
     *
     * @param string $tenantId The tenant ID to set
     * @return Model|null The tenant model if found, null otherwise
     */
    public function setTenantById(string $tenantId): ?Model
    {
        $tenantModel = config('multi-tenant.tenant_model');

        if (! $tenantModel || ! class_exists($tenantModel)) {
            return null;
        }

        $tenant = $tenantModel::find($tenantId);

        if ($tenant) {
            $this->setTenant($tenant);
        }

        return $tenant;
    }

    /**
     * Run a callback within a specific tenant context.
     *
     * @param Model $tenant The tenant to use
     * @param callable $callback The callback to run
     * @return mixed The callback result
     */
    public function runWithTenant(Model $tenant, callable $callback): mixed
    {
        $previousTenant = $this->tenant;
        $wasResolved = $this->resolved;

        $this->setTenant($tenant);

        try {
            return $callback($tenant);
        } finally {
            // Restore previous state
            $this->tenant = $previousTenant;
            $this->resolved = $wasResolved;
        }
    }

    /**
     * Run a callback within a specific tenant context (by ID).
     *
     * @param string $tenantId The tenant ID to use
     * @param callable $callback The callback to run
     * @return mixed The callback result
     * @throws \RuntimeException If tenant not found
     */
    public function runWithTenantId(string $tenantId, callable $callback): mixed
    {
        $tenantModel = config('multi-tenant.tenant_model');

        if (! $tenantModel || ! class_exists($tenantModel)) {
            throw new \RuntimeException('Tenant model not configured');
        }

        $tenant = $tenantModel::find($tenantId);

        if (! $tenant) {
            throw new \RuntimeException("Tenant not found: {$tenantId}");
        }

        return $this->runWithTenant($tenant, $callback);
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


