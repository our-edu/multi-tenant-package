<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Ouredu\MultiTenant\Commands\TenantAddTraitCommand;
use Ouredu\MultiTenant\Commands\TenantMigrateCommand;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Listeners\TenantQueryListener;
use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Ouredu\MultiTenant\Tenancy\TenantContext;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/multi-tenant.php', 'multi-tenant');

        // Bind ChainTenantResolver as the default TenantResolver
        // Uses bind() instead of singleton() for Octane compatibility
        // Users can override this in their AppServiceProvider if needed
        $this->app->bind(TenantResolver::class, ChainTenantResolver::class);

        // TenantContext is scoped (one instance per request) for Octane compatibility
        $this->app->scoped(TenantContext::class, function (Application $app): TenantContext {
            return new TenantContext($app->make(TenantResolver::class));
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerQueryListener();
    }

    /**
     * Register the package's commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantMigrateCommand::class,
                TenantAddTraitCommand::class,
            ]);
        }
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
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
     * Register the database query listener.
     */
    protected function registerQueryListener(): void
    {
        if (config('multi-tenant.query_listener.enabled', true)) {
            Event::listen(QueryExecuted::class, TenantQueryListener::class);
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
