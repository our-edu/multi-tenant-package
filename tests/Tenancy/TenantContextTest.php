<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Tenancy;

use Mockery;
use Mockery\MockInterface;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use RuntimeException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    private TenantContext $context;

    private TenantResolver|MockInterface $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = Mockery::mock(TenantResolver::class);
        $this->context = new TenantContext($this->resolver);
    }

    public function testGetTenantIdReturnsNullWhenNotResolved(): void
    {
        $this->resolver
            ->shouldReceive('resolveTenantId')
            ->once()
            ->andReturnNull();

        $tenantId = $this->context->getTenantId();

        $this->assertNull($tenantId);
    }

    public function testGetTenantIdReturnsTenantId(): void
    {
        $this->resolver
            ->shouldReceive('resolveTenantId')
            ->once()
            ->andReturn(123);

        $tenantId = $this->context->getTenantId();

        $this->assertEquals(123, $tenantId);
    }

    public function testSetTenantIdManually(): void
    {
        $this->context->setTenantId(456);

        $this->assertEquals(456, $this->context->getTenantId());
    }

    public function testSetTenantIdToNull(): void
    {
        $this->context->setTenantId(789);

        $this->context->setTenantId(null);

        $this->assertNull($this->context->getTenantId());
    }

    public function testHasTenantReturnsFalseWhenNoTenant(): void
    {
        $this->resolver
            ->shouldReceive('resolveTenantId')
            ->once()
            ->andReturnNull();

        $this->assertFalse($this->context->hasTenant());
    }

    public function testHasTenantReturnsTrueWhenTenantIdSet(): void
    {
        $this->context->setTenantId(1);

        $this->assertTrue($this->context->hasTenant());
    }

    public function testClearTenantContext(): void
    {
        $this->context->setTenantId(2);

        $this->assertTrue($this->context->hasTenant());

        $this->context->clear();

        $this->resolver
            ->shouldReceive('resolveTenantId')
            ->once()
            ->andReturnNull();

        $this->assertFalse($this->context->hasTenant());
    }

    public function testLazyLoadingTenantResolver(): void
    {
        $this->resolver
            ->shouldReceive('resolveTenantId')
            ->once()
            ->andReturn(100);

        // First call should resolve
        $tenantId1 = $this->context->getTenantId();

        // Second call should use cache (resolver should not be called again)
        $tenantId2 = $this->context->getTenantId();

        $this->assertSame($tenantId1, $tenantId2);
    }

    public function testRunForTenantExecutesCallbackWithTenantContext(): void
    {
        $this->context->setTenantId(1);

        $result = $this->context->runForTenant(2, function () {
            return $this->context->getTenantId();
        });

        $this->assertEquals(2, $result);
        $this->assertEquals(1, $this->context->getTenantId());
    }

    public function testRunForTenantRestoresContextAfterException(): void
    {
        $this->context->setTenantId(1);

        try {
            $this->context->runForTenant(2, function () {
                throw new RuntimeException('Test exception');
            });
        } catch (RuntimeException) {
            // Expected
        }

        $this->assertEquals(1, $this->context->getTenantId());
    }
}
