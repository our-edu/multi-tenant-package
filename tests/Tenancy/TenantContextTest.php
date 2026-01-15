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
            ->andReturn('test-uuid-123');

        $tenantId = $this->context->getTenantId();

        $this->assertEquals('test-uuid-123', $tenantId);
    }

    public function testSetTenantIdManually(): void
    {
        $this->context->setTenantId('manual-uuid');

        $this->assertEquals('manual-uuid', $this->context->getTenantId());
    }

    public function testSetTenantIdToNull(): void
    {
        $this->context->setTenantId('temp-uuid');

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
        $this->context->setTenantId('has-tenant-uuid');

        $this->assertTrue($this->context->hasTenant());
    }

    public function testClearTenantContext(): void
    {
        $this->context->setTenantId('clear-uuid');

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
            ->andReturn('lazy-uuid');

        // First call should resolve
        $tenantId1 = $this->context->getTenantId();

        // Second call should use cache (resolver should not be called again)
        $tenantId2 = $this->context->getTenantId();

        $this->assertSame($tenantId1, $tenantId2);
    }

    public function testRunForTenantExecutesCallbackWithTenantContext(): void
    {
        $this->context->setTenantId('original-tenant');

        $result = $this->context->runForTenant('temporary-tenant', function () {
            return $this->context->getTenantId();
        });

        $this->assertEquals('temporary-tenant', $result);
        $this->assertEquals('original-tenant', $this->context->getTenantId());
    }

    public function testRunForTenantRestoresContextAfterException(): void
    {
        $this->context->setTenantId('original-tenant');

        try {
            $this->context->runForTenant('temporary-tenant', function () {
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertEquals('original-tenant', $this->context->getTenantId());
    }
}
