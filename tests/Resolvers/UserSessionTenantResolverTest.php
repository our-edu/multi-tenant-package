<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Resolvers;

use Ouredu\MultiTenant\Resolvers\UserSessionTenantResolver;
use Tests\TestCase;

class UserSessionTenantResolverTest extends TestCase
{
    public function testResolveTenantIdReturnsNullWhenGetSessionNotDefined(): void
    {
        $resolver = new UserSessionTenantResolver();

        $tenantId = $resolver->resolveTenantId();

        $this->assertNull($tenantId);
    }

    public function testResolveTenantIdReturnsNullInConsole(): void
    {
        // PHPUnit runs in console, so this should return null
        $resolver = new UserSessionTenantResolver();

        $tenantId = $resolver->resolveTenantId();

        $this->assertNull($tenantId);
    }

    public function testGetTenantColumnDefaultsToTenantId(): void
    {
        $resolver = new class () extends UserSessionTenantResolver {
            public function exposedGetTenantColumn(): string
            {
                return $this->getTenantColumn();
            }
        };

        $this->assertEquals('tenant_id', $resolver->exposedGetTenantColumn());
    }

    public function testGetTenantColumnUsesConfig(): void
    {
        config(['multi-tenant.session.tenant_column' => 'custom_tenant_id']);

        $resolver = new class () extends UserSessionTenantResolver {
            public function exposedGetTenantColumn(): string
            {
                return $this->getTenantColumn();
            }
        };

        $this->assertEquals('custom_tenant_id', $resolver->exposedGetTenantColumn());
    }

    public function testGetSessionHelperNameDefaultsToGetSession(): void
    {
        $resolver = new class () extends UserSessionTenantResolver {
            public function exposedGetSessionHelperName(): string
            {
                return $this->getSessionHelperName();
            }
        };

        $this->assertEquals('getSession', $resolver->exposedGetSessionHelperName());
    }

    public function testGetSessionHelperNameUsesConfig(): void
    {
        config(['multi-tenant.session.helper' => 'customSessionHelper']);

        $resolver = new class () extends UserSessionTenantResolver {
            public function exposedGetSessionHelperName(): string
            {
                return $this->getSessionHelperName();
            }
        };

        $this->assertEquals('customSessionHelper', $resolver->exposedGetSessionHelperName());
    }
}

