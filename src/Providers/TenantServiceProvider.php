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
use Ouredu\MultiTenant\Commands\TenantAddListenerTraitCommand;
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
        $this->app->bind(TenantResolver::class, ChainTenantResolver::class);

        // Scoped binding for TenantContext
        $this->app->scoped(TenantContext::class, fn (Application $app): TenantContext => new TenantContext($app->make(TenantResolver::class)));
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerQueryListener();
        $this->registerTranslations();
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
                TenantAddListenerTraitCommand::class,
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
            $this->publishes([$configPath => $publishPath], 'config');

            // Auto-copy the config file
            if (! $this->app->configurationIsCached()) {
                copy($configPath, $publishPath);
            }
        } else {
            // Still register for manual publishing
            $this->publishes([$configPath => $publishPath], 'config');
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

    /**
     * Get the lang directory path.
     */
    protected function langPath(): string
    {
        return dirname(__DIR__, 2) . '/lang';
    }

    /**
     * Register the package's translations.
     */
    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom($this->langPath(), 'multi-tenant');

        $publishPath = $this->app->langPath('vendor/multi-tenant');

        $this->publishes([$this->langPath() => $publishPath], 'multi-tenant-lang');

        // Auto-publish lang files if they don't exist
        if (! is_dir($publishPath) && is_dir($this->langPath())) {
            $this->autoPublishLanguageFiles($publishPath);
        }
    }

    /**
     * Auto-publish language files to the application's lang directory.
     */
    protected function autoPublishLanguageFiles(string $publishPath): void
    {
        $sourcePath = $this->langPath();

        // Create the vendor directory if it doesn't exist
        if (! is_dir($publishPath)) {
            @mkdir($publishPath, 0o755, true);
        }

        foreach (scandir($sourcePath) as $langDir) {
            if (in_array($langDir, ['.', '..'], true)) {
                continue;
            }

            $sourceLangPath = "$sourcePath/$langDir";
            $targetLangPath = "$publishPath/$langDir";

            if (is_dir($sourceLangPath)) {
                if (! is_dir($targetLangPath)) {
                    @mkdir($targetLangPath, 0o755, true);
                }

                foreach (scandir($sourceLangPath) as $file) {
                    if (in_array($file, ['.', '..'], true)) {
                        continue;
                    }

                    $sourceFile = "$sourceLangPath/$file";
                    $targetFile = "$targetLangPath/$file";

                    if (is_file($sourceFile) && ! file_exists($targetFile)) {
                        @copy($sourceFile, $targetFile);
                    }
                }
            }
        }
    }
}
