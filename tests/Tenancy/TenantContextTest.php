<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Tenancy;

use Illuminate\Database\Eloquent\Model;
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

    public function testGetTenantReturnsNullWhenNotResolved(): void
    {
        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturnNull();

        $tenant = $this->context->getTenant();

        $this->assertNull($tenant);
    }

    public function testGetTenantReturnsTenantModel(): void
    {
        $tenantModel = $this->createTenantMock('test-uuid-123');

        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturn($tenantModel);

        $tenant = $this->context->getTenant();

        $this->assertSame($tenantModel, $tenant);
    }

    public function testGetTenantIdReturnsUuidWhenAvailable(): void
    {
        $tenantModel = $this->createTenantMock('test-uuid-456');

        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturn($tenantModel);

        $tenantId = $this->context->getTenantId();

        $this->assertEquals('test-uuid-456', $tenantId);
    }

    public function testGetTenantIdReturnsPrimaryKeyWhenUuidNotAvailable(): void
    {
        $tenantModel = $this->createTenantMock(null, 789);

        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturn($tenantModel);

        $tenantId = $this->context->getTenantId();

        $this->assertEquals('789', $tenantId);
    }

    public function testGetTenantIdReturnsNullWhenNoTenant(): void
    {
        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturnNull();

        $tenantId = $this->context->getTenantId();

        $this->assertNull($tenantId);
    }

    public function testSetTenantManually(): void
    {
        $tenantModel = $this->createTenantMock('manual-uuid');

        $this->context->setTenant($tenantModel);

        $this->assertSame($tenantModel, $this->context->getTenant());
        $this->assertEquals('manual-uuid', $this->context->getTenantId());
    }

    public function testSetTenantToNull(): void
    {
        $tenantModel = $this->createTenantMock('temp-uuid');
        $this->context->setTenant($tenantModel);

        $this->context->setTenant(null);

        $this->assertNull($this->context->getTenant());
    }

    public function testHasTenantReturnsFalseWhenNoTenant(): void
    {
        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturnNull();

        $this->assertFalse($this->context->hasTenant());
    }

    public function testHasTenantReturnsTrueWhenTenantSet(): void
    {
        $tenantModel = $this->createTenantMock('has-tenant-uuid');
        $this->context->setTenant($tenantModel);

        $this->assertTrue($this->context->hasTenant());
    }

    public function testClearTenantContext(): void
    {
        $tenantModel = $this->createTenantMock('clear-uuid');
        $this->context->setTenant($tenantModel);

        $this->assertTrue($this->context->hasTenant());

        $this->context->clear();

        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturnNull();

        $this->assertFalse($this->context->hasTenant());
    }

    public function testLazyLoadingTenantResolver(): void
    {
        $tenantModel = $this->createTenantMock('lazy-uuid');

        $this->resolver
            ->shouldReceive('resolveTenant')
            ->once()
            ->andReturn($tenantModel);

        // First call should resolve
        $tenant1 = $this->context->getTenant();

        // Second call should use cache (resolver should not be called again)
        $tenant2 = $this->context->getTenant();

        $this->assertSame($tenant1, $tenant2);
    }

    /**
     * Create a mock tenant model with given uuid and/or key
     */
    private function createTenantMock(?string $uuid, int $key = 1): MockInterface
    {
        $mock = Mockery::mock(Model::class)->makePartial();
        $mock->shouldReceive('getKey')->andReturn($key);

        // Set uuid as a real property on the mock
        $mock->forceFill(['uuid' => $uuid]);

        return $mock;
    }
}

