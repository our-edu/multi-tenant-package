<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Contracts;

use Ouredu\MultiTenant\Contracts\TenantResolver;
use Tests\TestCase;

class TenantResolverTest extends TestCase
{
    public function testTenantResolverIsInterface(): void
    {
        $this->assertTrue(interface_exists(TenantResolver::class));
    }

    public function testTenantResolverCanBeImplemented(): void
    {
        $resolver = new class () implements TenantResolver {
            public function resolveTenantId(): ?string
            {
                return null;
            }
        };

        $this->assertInstanceOf(TenantResolver::class, $resolver);
    }

    public function testTenantResolverReturnsNullForNoTenant(): void
    {
        $resolver = new class () implements TenantResolver {
            public function resolveTenantId(): ?string
            {
                return null;
            }
        };

        $result = $resolver->resolveTenantId();

        $this->assertNull($result);
    }

    public function testTenantResolverReturnsTenantId(): void
    {
        $resolver = new class () implements TenantResolver {
            public function resolveTenantId(): ?string
            {
                return 'test-tenant-uuid-123';
            }
        };

        $result = $resolver->resolveTenantId();

        $this->assertEquals('test-tenant-uuid-123', $result);
    }
}
