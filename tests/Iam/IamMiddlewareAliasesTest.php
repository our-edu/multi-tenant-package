<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Routing\Router;
use Ouredu\MultiTenant\Iam\Middleware\PermissionMiddleware;
use Ouredu\MultiTenant\Iam\Middleware\RoleMiddleware;
use Tests\TestCase;

class IamMiddlewareAliasesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('multi-tenant.iam.register_middleware_aliases', true);
    }

    public function testMiddlewareAliasesAreRegisteredWhenOptedIn(): void
    {
        $aliases = $this->app->make(Router::class)->getMiddleware();

        $this->assertSame(RoleMiddleware::class, $aliases['role']);
        $this->assertSame(PermissionMiddleware::class, $aliases['permission']);
    }
}
