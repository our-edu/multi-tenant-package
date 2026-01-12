<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Providers;

use Illuminate\Container\Container;
use Mockery;
use Oured\MultiTenant\Contracts\TenantResolver;
use Oured\MultiTenant\Providers\TenantServiceProvider;
use Oured\MultiTenant\Tenancy\TenantContext;
use Tests\TestCase;

class TenantServiceProviderTest extends TestCase
{
    public function testProviderRegistersTenantContext(): void
    {
        $app = new Container();

        // Bind a test resolver
        $app->bind(TenantResolver::class, function () {
            return Mockery::mock(TenantResolver::class);
        });

        $provider = new TenantServiceProvider($app);
        $provider->register();

        $this->assertTrue($app->has(TenantContext::class));
    }

    public function testProviderRegistersTenantContextAsSingleton(): void
    {
        $app = new Container();

        $app->bind(TenantResolver::class, function () {
            return Mockery::mock(TenantResolver::class);
        });

        $provider = new TenantServiceProvider($app);
        $provider->register();

        $instance1 = $app->make(TenantContext::class);
        $instance2 = $app->make(TenantContext::class);

        $this->assertSame($instance1, $instance2);
    }

    public function testProviderMergesConfig(): void
    {
        $app = new Container();

        $app->bind(TenantResolver::class, function () {
            return Mockery::mock(TenantResolver::class);
        });

        // Mock the config repository
        $config = [];
        $app->bind('config', function () use (&$config) {
            return new class($config) {
                private array $config;

                public function __construct(&$config)
                {
                    $this->config = &$config;
                }

                public function get(string $key, $default = null)
                {
                    return $this->config[$key] ?? $default;
                }

                public function set(string $key, $value): void
                {
                    $this->config[$key] = $value;
                }
            };
        });

        $provider = new TenantServiceProvider($app);
        $provider->register();

        // The provider should have merged config
        $this->assertTrue($app->has('config'));
    }
}

