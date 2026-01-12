<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Oured\MultiTenant\Traits;

use Oured\MultiTenant\Tenancy\TenantContext;

/**
 * TenantAwareJob
 *
 * Trait for queued jobs that need tenant context.
 * Automatically captures the current tenant when the job is created
 * and restores it when the job is processed.
 *
 * Usage:
 *   class MyJob implements ShouldQueue
 *   {
 *       use TenantAwareJob;
 *
 *       public function handle(): void
 *       {
 *           // Tenant context is automatically set
 *       }
 *   }
 */
trait TenantAwareJob
{
    /**
     * The tenant ID for this job.
     */
    public ?string $tenantId = null;

    /**
     * Set the tenant ID explicitly (for dispatching to specific tenant).
     */
    public function forTenant(string $tenantId): static
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Capture the current tenant context.
     * Call this in your job's constructor.
     */
    protected function captureTenantContext(): void
    {
        if ($this->tenantId === null) {
            $context = app(TenantContext::class);
            $this->tenantId = $context->getTenantId();
        }
    }

    /**
     * Get the tenant ID for this job.
     */
    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    /**
     * Check if job has tenant context.
     */
    public function hasTenantContext(): bool
    {
        return $this->tenantId !== null;
    }
}

