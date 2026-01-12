<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Mockery;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Oured\MultiTenant\Contracts\TenantResolver;
use Oured\MultiTenant\Providers\TenantServiceProvider;

/**
 * Base TestCase for all multi-tenant package tests
 */
abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [
            TenantServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        // Setup default config
        $app['config']->set('multi-tenant.tenant_model', 'App\\Models\\Tenant');
        $app['config']->set('multi-tenant.tenant_column', 'tenant_id');

        // Bind a default resolver that returns null
        $app->bind(TenantResolver::class, function () {
            return new class implements TenantResolver {
                public function resolveTenant(): ?Model
                {
                    return null;
                }
            };
        });
    }
}

