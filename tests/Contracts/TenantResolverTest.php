<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Contracts;

use Illuminate\Database\Eloquent\Model;
use Mockery;
use Oured\MultiTenant\Contracts\TenantResolver;
use Tests\TestCase;

class TenantResolverTest extends TestCase
{
    public function testTenantResolverIsInterface(): void
    {
        $this->assertTrue(interface_exists(TenantResolver::class));
    }

    public function testTenantResolverCanBeImplemented(): void
    {
        $resolver = new class implements TenantResolver {
            public function resolveTenant(): ?Model
            {
                return null;
            }
        };

        $this->assertInstanceOf(TenantResolver::class, $resolver);
    }

    public function testTenantResolverReturnsNullForNoTenant(): void
    {
        $resolver = new class implements TenantResolver {
            public function resolveTenant(): ?Model
            {
                return null;
            }
        };

        $result = $resolver->resolveTenant();

        $this->assertNull($result);
    }

    public function testTenantResolverReturnsModel(): void
    {
        $tenantModel = Mockery::mock(Model::class);

        $resolver = new class($tenantModel) implements TenantResolver {
            private Model $tenant;

            public function __construct(Model $tenant)
            {
                $this->tenant = $tenant;
            }

            public function resolveTenant(): ?Model
            {
                return $this->tenant;
            }
        };

        $result = $resolver->resolveTenant();

        $this->assertSame($tenantModel, $result);
    }
}

