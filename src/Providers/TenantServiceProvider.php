<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Ouredu\MultiTenant\Tenancy\TenantContext;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/multi-tenant.php', 'multi-tenant');

        // Register a default TenantResolver if the host application hasn't bound one
        $this->registerResolver();

        $this->app->scoped(TenantContext::class, function (Application $app): TenantContext {
            /** @var TenantResolver $resolver */
            $resolver = $app->make(TenantResolver::class);

            return new TenantContext($resolver);
        });
    }

    /**
     * Register the TenantResolver binding.
     *
     * If a resolver is already bound by the host application, we do nothing.
     * Otherwise, we look at the configured resolver class and fall back to
     * ChainTenantResolver when needed.
     */
    protected function registerResolver(): void
    {
        // Do not override an explicit binding from the host application
        if ($this->app->bound(TenantResolver::class)) {
            return;
        }

        $resolverClass = config('multi-tenant.resolver');

        // If resolver is explicitly set to null, the host must bind its own
        if ($resolverClass === null) {
            return;
        }

        if (is_string($resolverClass) && class_exists($resolverClass)) {
            $this->app->bind(TenantResolver::class, $resolverClass);
        } else {
            // Fallback to the default chain resolver
            $this->app->bind(TenantResolver::class, ChainTenantResolver::class);
        }
    }

    public function boot(): void
    {
        $configPath = $this->configPath();
        $publishPath = $this->app->configPath('multi-tenant.php');

        // Auto-publish config if it doesn't exist
        if (! file_exists($publishPath) && file_exists($configPath)) {
            $this->publishes([
                $configPath => $publishPath,
            ], 'config');

            // Auto-copy the config file
            if (! $this->app->configurationIsCached()) {
                copy($configPath, $publishPath);
            }
        } else {
            // Still register for manual publishing
            $this->publishes([
                $configPath => $publishPath,
            ], 'config');
        }
    }

    /**
     * Get the config file path.
     */
    protected function configPath(): string
    {
        return dirname(__DIR__, 2) . '/config/multi-tenant.php';
    }
}
