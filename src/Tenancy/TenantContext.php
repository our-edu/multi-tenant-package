<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Tenancy;

use Ouredu\MultiTenant\Contracts\TenantResolver;

/**
 * TenantContext
 *
 * Shared tenant context service used across the request / job / command
 * lifecycle. It relies on a TenantResolver implementation (bound in the
 * host application) to actually figure out the current tenant ID.
 *
 * Responsibilities:
 * - Cache the current tenant ID for the lifetime of the request/job
 * - Provide helper methods (getTenantId, hasTenant, clear, manual set)
 */
class TenantContext
{
    private ?int $tenantId = null;

    private bool $resolved = false;

    public function __construct(
        private readonly TenantResolver $resolver
    ) {
    }

    /**
     * Get the current tenant ID (or null if not resolved).
     */
    public function getTenantId(): ?int
    {
        if (! $this->resolved) {
            $this->resolve();
        }

        return $this->tenantId;
    }

    /**
     * Manually set the tenant ID (useful for tests, CLI, jobs, or messages).
     */
    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->resolved = true;
    }

    /**
     * Clear the tenant context.
     */
    public function clear(): void
    {
        $this->tenantId = null;
        $this->resolved = false;
    }

    /**
     * Check if tenant is set.
     */
    public function hasTenant(): bool
    {
        return $this->getTenantId() !== null;
    }

    /**
     * Run a callback within a specific tenant context.
     *
     * @template TReturn
     * @param int $tenantId
     * @param callable(): TReturn $callback
     * @return TReturn
     */
    public function runForTenant(int $tenantId, callable $callback): mixed
    {
        $previousTenantId = $this->tenantId;
        $previousResolved = $this->resolved;

        try {
            $this->setTenantId($tenantId);

            return $callback();
        } finally {
            $this->tenantId = $previousTenantId;
            $this->resolved = $previousResolved;
        }
    }

    /**
     * Perform lazy resolution using the configured TenantResolver.
     */
    private function resolve(): void
    {
        $this->resolved = true;
        $this->tenantId = $this->resolver->resolveTenantId();
    }
}
