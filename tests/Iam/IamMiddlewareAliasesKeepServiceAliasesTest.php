<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Routing\Router;
use Ouredu\MultiTenant\Iam\Middleware\PermissionMiddleware;
use Tests\TestCase;

class IamMiddlewareAliasesKeepServiceAliasesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('multi-tenant.iam.register_middleware_aliases', true);

        // What the service's HTTP Kernel syncs before the package boots
        $app->make(Router::class)->aliasMiddleware('role', ServiceRoleMiddleware::class);
    }

    public function testTheServicesOwnAliasIsKept(): void
    {
        $aliases = $this->app->make(Router::class)->getMiddleware();

        $this->assertSame(ServiceRoleMiddleware::class, $aliases['role']);
        $this->assertSame(PermissionMiddleware::class, $aliases['permission']);
    }
}

class ServiceRoleMiddleware
{
}
