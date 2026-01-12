<?php

declare(strict_types=1);

namespace Oured\MultiTenant\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Oured\MultiTenant\Contracts\TenantResolver;
use Oured\MultiTenant\Tenancy\TenantContext;

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
        $this->publishes([
            __DIR__.'/../../config/multi-tenant.php' => config_path('multi-tenant.php'),
        ], 'config');
    }
}


