<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Oured\MultiTenant\Traits;

use Illuminate\Database\Eloquent\Model;
use Oured\MultiTenant\Tenancy\TenantContext;

/**
 * TenantAwareCommand
 *
 * Trait for Artisan commands that need tenant context.
 * Provides utilities for running commands for specific tenants
 * or iterating over all tenants.
 *
 * Usage:
 *   class MyCommand extends Command
 *   {
 *       use TenantAwareCommand;
 *
 *       protected $signature = 'my:command {--tenant= : Tenant ID}';
 *
 *       public function handle(): int
 *       {
 *           if ($this->option('tenant')) {
 *               return $this->runForTenant();
 *           }
 *           return $this->runForAllTenants();
 *       }
 *   }
 *
 * @method mixed option(string|null $key = null)
 * @method void info(string $string, int|string|null $verbosity = null)
 * @method void error(string $string, int|string|null $verbosity = null)
 * @method void newLine(int $count = 1)
 */
trait TenantAwareCommand
{
    /**
     * Initialize tenant context from --tenant option.
     *
     * @return Model|null The tenant model, or null if not found
     */
    protected function initializeTenantFromOption(): ?Model
    {
        $tenantId = $this->option('tenant');

        if (! $tenantId) {
            return null;
        }

        return $this->setTenantById($tenantId);
    }

    /**
     * Set tenant context by ID.
     *
     * @param string $tenantId The tenant ID
     * @return Model|null The tenant model, or null if not found
     */
    protected function setTenantById(string $tenantId): ?Model
    {
        $tenantModel = config('multi-tenant.tenant_model');

        if (! $tenantModel || ! class_exists($tenantModel)) {
            $this->error('Tenant model not configured');
            return null;
        }

        $tenant = $tenantModel::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant not found: {$tenantId}");
            return null;
        }

        app(TenantContext::class)->setTenant($tenant);
        $this->info("Running for tenant: " . ($tenant->name ?? $tenantId));

        return $tenant;
    }

    /**
     * Run a callback for each tenant.
     *
     * @param callable $callback The callback to run for each tenant
     * @param bool $stopOnError Whether to stop if an error occurs
     * @return int Number of successful runs
     */
    protected function forEachTenant(callable $callback, bool $stopOnError = false): int
    {
        $tenantModel = config('multi-tenant.tenant_model');

        if (! $tenantModel || ! class_exists($tenantModel)) {
            $this->error('Tenant model not configured');
            return 0;
        }

        $tenants = $tenantModel::all();
        $successCount = 0;

        foreach ($tenants as $tenant) {
            $tenantName = $tenant->name ?? $tenant->getKey();
            $this->info("Processing tenant: {$tenantName}");

            app(TenantContext::class)->setTenant($tenant);

            try {
                $callback($tenant);
                $successCount++;
                $this->info("✓ Completed: {$tenantName}");
            } catch (\Throwable $e) {
                $this->error("✗ Error for tenant {$tenantName}: {$e->getMessage()}");

                if ($stopOnError) {
                    app(TenantContext::class)->clear();
                    throw $e;
                }
            } finally {
                app(TenantContext::class)->clear();
            }
        }

        $this->newLine();
        $this->info("Completed: {$successCount}/{$tenants->count()} tenants");

        return $successCount;
    }

    /**
     * Run command for specific tenant or all tenants.
     *
     * @param callable $callback The callback to run
     * @return int Command exit code (0 = success, 1 = failure)
     */
    protected function runForTenantOrAll(callable $callback): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenant = $this->setTenantById($tenantId);

            if (! $tenant) {
                return 1; // FAILURE
            }

            try {
                $callback($tenant);
                return 0; // SUCCESS
            } finally {
                app(TenantContext::class)->clear();
            }
        }

        $this->forEachTenant($callback);
        return 0; // SUCCESS
    }

    /**
     * Get the current tenant context.
     */
    protected function getTenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }

    /**
     * Clear the tenant context.
     */
    protected function clearTenantContext(): void
    {
        app(TenantContext::class)->clear();
    }
}

