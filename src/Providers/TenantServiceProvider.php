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
use Ouredu\MultiTenant\Tenancy\TenantContext;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/multi-tenant.php', 'multi-tenant');

        $this->app->singleton(TenantContext::class, function (Application $app): TenantContext {
            /** @var TenantResolver $resolver */
            $resolver = $app->make(TenantResolver::class);

            return new TenantContext($resolver);
        });
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


