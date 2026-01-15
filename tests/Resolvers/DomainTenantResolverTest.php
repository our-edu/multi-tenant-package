<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Resolvers;

use Ouredu\MultiTenant\Resolvers\DomainTenantResolver;
use Tests\TestCase;

class DomainTenantResolverTest extends TestCase
{
    public function testResolveTenantIdReturnsNullInConsole(): void
    {
        // PHPUnit runs in console, so this should return null
        $resolver = new DomainTenantResolver();

        $tenantId = $resolver->resolveTenantId();

        $this->assertNull($tenantId);
    }

    public function testGetDomainColumnDefaultsToDomain(): void
    {
        $resolver = new class () extends DomainTenantResolver {
            public function exposedGetDomainColumn(): string
            {
                return $this->getDomainColumn();
            }
        };

        $this->assertEquals('domain', $resolver->exposedGetDomainColumn());
    }

    public function testGetDomainColumnUsesConfig(): void
    {
        config(['multi-tenant.domain.column' => 'custom_domain']);

        $resolver = new class () extends DomainTenantResolver {
            public function exposedGetDomainColumn(): string
            {
                return $this->getDomainColumn();
            }
        };

        $this->assertEquals('custom_domain', $resolver->exposedGetDomainColumn());
    }

    public function testGetTenantModelUsesConfig(): void
    {
        config(['multi-tenant.tenant_model' => 'App\\Models\\CustomTenant']);

        $resolver = new class () extends DomainTenantResolver {
            public function exposedGetTenantModel(): ?string
            {
                return $this->getTenantModel();
            }
        };

        $this->assertEquals('App\\Models\\CustomTenant', $resolver->exposedGetTenantModel());
    }
}
