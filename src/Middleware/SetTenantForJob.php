<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Oured\MultiTenant\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Oured\MultiTenant\Tenancy\TenantContext;

/**
 * SetTenantForJob
 *
 * Job middleware that sets the tenant context from the job's tenantId property.
 * Use this with jobs that use the TenantAwareJob trait.
 *
 * Usage in job:
 *   public function middleware(): array
 *   {
 *       return [new SetTenantForJob()];
 *   }
 */
class SetTenantForJob
{
    /**
     * The tenant model class to use for lookups.
     * Override this in your service's config.
     */
    protected ?string $tenantModel = null;

    public function __construct(?string $tenantModel = null)
    {
        $this->tenantModel = $tenantModel ?? config('multi-tenant.tenant_model');
    }

    /**
     * Process the job.
     */
    public function handle(object $job, Closure $next): mixed
    {
        $tenant = $this->resolveTenant($job);

        if ($tenant) {
            app(TenantContext::class)->setTenant($tenant);
        }

        try {
            return $next($job);
        } finally {
            // Always clear context after job completes
            app(TenantContext::class)->clear();
        }
    }

    /**
     * Resolve tenant from job.
     */
    protected function resolveTenant(object $job): ?Model
    {
        // Check for tenantId property (from TenantAwareJob trait)
        if (property_exists($job, 'tenantId') && $job->tenantId) {
            return $this->findTenant($job->tenantId);
        }

        // Check for tenant_id property (alternative naming)
        if (property_exists($job, 'tenant_id') && $job->tenant_id) {
            return $this->findTenant($job->tenant_id);
        }

        // Check for getTenantId method
        if (method_exists($job, 'getTenantId')) {
            $tenantId = $job->getTenantId();
            if ($tenantId) {
                return $this->findTenant($tenantId);
            }
        }

        return null;
    }

    /**
     * Find tenant by ID.
     */
    protected function findTenant(string $tenantId): ?Model
    {
        if (! $this->tenantModel || ! class_exists($this->tenantModel)) {
            return null;
        }

        return $this->tenantModel::find($tenantId);
    }
}

