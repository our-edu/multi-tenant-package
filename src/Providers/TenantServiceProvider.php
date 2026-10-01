<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Providers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\PresenceVerifierInterface;
use Ouredu\MultiTenant\Commands\SetTenantIdCommand;
use Ouredu\MultiTenant\Commands\TenantAddListenerTraitCommand;
use Ouredu\MultiTenant\Commands\TenantAddTraitCommand;
use Ouredu\MultiTenant\Commands\TenantMigrateCommand;
use Ouredu\MultiTenant\Commands\TimezoneCheckCommand;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Iam\IamConfig;
use Ouredu\MultiTenant\Iam\Middleware\PermissionMiddleware;
use Ouredu\MultiTenant\Iam\Middleware\RoleMiddleware;
use Ouredu\MultiTenant\Iam\PermissionAuthorizer;
use Ouredu\MultiTenant\Iam\TokenClaimsResolver;
use Ouredu\MultiTenant\Listeners\TenantQueryListener;
use Ouredu\MultiTenant\Middleware\TenantMiddleware;
use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Ouredu\MultiTenant\Timezone\TimezoneContext;
use Ouredu\MultiTenant\Validation\TenantDatabasePresenceVerifier;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/multi-tenant.php', 'multi-tenant');

        // Bind ChainTenantResolver as the default TenantResolver
        $this->app->bind(TenantResolver::class, ChainTenantResolver::class);

        // Scoped binding for TenantContext
        $this->app->scoped(TenantContext::class, fn (Application $app): TenantContext => new TenantContext($app->make(TenantResolver::class)));

        // Scoped binding for TimezoneContext (per request / per job, like TenantContext)
        $this->app->scoped(TimezoneContext::class, fn (Application $app): TimezoneContext => new TimezoneContext($app));

        // Scoped: one IAM call for the claims, and one per permission, per request (Octane safe)
        $this->app->scoped(TokenClaimsResolver::class);
        $this->app->scoped(PermissionAuthorizer::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerQueryListener();
        $this->registerValidationPresenceVerifier();
        $this->registerTranslations();
        $this->registerMiddleware();
        $this->registerIamMiddlewareAliases();
        $this->registerCarbonMacros();
    }

    /**
     * Register the `role` and `permission` middleware aliases, when opted in.
     * Only aliases the service has not defined are added: the HTTP Kernel
     * syncs its aliases before providers boot, and a later aliasMiddleware()
     * call would silently replace the service's own middleware.
     */
    protected function registerIamMiddlewareAliases(): void
    {
        if (!IamConfig::get('register_middleware_aliases')) {
            return;
        }

        $router = $this->app->make(Router::class);
        $existing = $router->getMiddleware();

        foreach (['role' => RoleMiddleware::class, 'permission' => PermissionMiddleware::class] as $name => $class) {
            if (!array_key_exists($name, $existing)) {
                $router->aliasMiddleware($name, $class);
            }
        }
    }

    /**
     * Register the `inTenantTz()` Carbon macro on Carbon and CarbonImmutable.
     *
     * `now()->inTenantTz()` returns a copy in the current tenant zone;
     * `->inTenantTz('Africa/Cairo')` in an explicit zone (jobs, cron).
     * A plain closure is required: Carbon rebinds `$this` to the date instance.
     */
    protected function registerCarbonMacros(): void
    {
        $macro = function (?string $timezone = null) {
            /** @var \Carbon\CarbonInterface $this */
            return $this->copy()->setTimezone($timezone ?? TenantTimezone::current());
        };

        foreach ([Carbon::class, CarbonImmutable::class] as $class) {
            if (! $class::hasMacro('inTenantTz')) {
                $class::macro('inTenantTz', $macro);
            }
        }
    }

    /**
     * Register the tenant middleware.
     */
    protected function registerMiddleware(): void
    {
        if (config('multi-tenant.middleware.enabled', true)) {
            /** @var \Illuminate\Foundation\Http\Kernel $kernel */
            $kernel = $this->app->make(Kernel::class);

            // Register as global middleware (high priority - runs early)
            $kernel->prependMiddleware(TenantMiddleware::class);
        }
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
                SetTenantIdCommand::class,
                TimezoneCheckCommand::class,
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
     * Register tenant-aware database validation for exists/unique rules.
     */
    protected function registerValidationPresenceVerifier(): void
    {
        if (! class_exists(ValidationFactory::class)) {
            return;
        }

        $this->app->booted(function (Application $app): void {
            $presenceVerifier = new TenantDatabasePresenceVerifier(
                $app->make('db'),
                $app
            );

            $app->instance('validation.presence', $presenceVerifier);

            if (interface_exists(PresenceVerifierInterface::class)) {
                $app->instance(PresenceVerifierInterface::class, $presenceVerifier);
            }

            if ($app->bound('validator')) {
                /** @var ValidationFactory $validator */
                $validator = $app->make('validator');
                $validator->setPresenceVerifier($presenceVerifier);
            }
        });
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
