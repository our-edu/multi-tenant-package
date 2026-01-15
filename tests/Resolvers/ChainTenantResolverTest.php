<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Resolvers;

use Mockery;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Tests\TestCase;

class ChainTenantResolverTest extends TestCase
{
    public function testResolveTenantIdReturnsNullWhenNoResolversMatch(): void
    {
        $resolver1 = Mockery::mock(TenantResolver::class);
        $resolver1->shouldReceive('resolveTenantId')->andReturnNull();

        $resolver2 = Mockery::mock(TenantResolver::class);
        $resolver2->shouldReceive('resolveTenantId')->andReturnNull();

        $chain = new ChainTenantResolver([$resolver1, $resolver2]);

        $tenantId = $chain->resolveTenantId();

        $this->assertNull($tenantId);
    }

    public function testResolveTenantIdReturnsFirstMatchingResult(): void
    {
        $resolver1 = Mockery::mock(TenantResolver::class);
        $resolver1->shouldReceive('resolveTenantId')->andReturn(1);

        $resolver2 = Mockery::mock(TenantResolver::class);
        $resolver2->shouldNotReceive('resolveTenantId');

        $chain = new ChainTenantResolver([$resolver1, $resolver2]);

        $tenantId = $chain->resolveTenantId();

        $this->assertEquals(1, $tenantId);
    }

    public function testResolveTenantIdTriesSecondResolverIfFirstReturnsNull(): void
    {
        $resolver1 = Mockery::mock(TenantResolver::class);
        $resolver1->shouldReceive('resolveTenantId')->andReturnNull();

        $resolver2 = Mockery::mock(TenantResolver::class);
        $resolver2->shouldReceive('resolveTenantId')->andReturn(2);

        $chain = new ChainTenantResolver([$resolver1, $resolver2]);

        $tenantId = $chain->resolveTenantId();

        $this->assertEquals(2, $tenantId);
    }

    public function testChainResolverUsesDefaultResolversWhenNoneProvided(): void
    {
        $chain = new ChainTenantResolver();

        // Should not throw - just return null since we're in console mode
        $tenantId = $chain->resolveTenantId();

        $this->assertNull($tenantId);
    }
}

