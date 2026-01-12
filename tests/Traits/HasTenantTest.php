<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use Mockery\MockInterface;
use Oured\MultiTenant\Tenancy\TenantContext;
use Oured\MultiTenant\Traits\HasTenant;
use Tests\TestCase;

class HasTenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('getTenantId')->andReturn('test-tenant-id');
        $this->app->instance(TenantContext::class, $context);
    }

    public function testResolveTenantColumnDefaultsToTenantId(): void
    {
        $model = new TestModel();

        $column = $model->getTenantColumnForTest();

        $this->assertEquals('tenant_id', $column);
    }

    public function testResolveTenantColumnUsesCustomMethod(): void
    {
        $model = new TestModelWithCustomColumn();

        $column = $model->getTenantColumnForTest();

        $this->assertEquals('organization_id', $column);
    }

    public function testScopeForTenant(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('test_models.tenant_id', 'tenant-123')
            ->once()
            ->andReturnSelf();

        $model = new TestModel();
        $result = $model->scopeForTenant($builder, 'tenant-123');

        $this->assertSame($builder, $result);
    }

    public function testScopeForTenantWithCustomColumn(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')
            ->with('test_models_custom.organization_id', 'org-456')
            ->once()
            ->andReturnSelf();

        $model = new TestModelWithCustomColumn();
        $result = $model->scopeForTenant($builder, 'org-456');

        $this->assertSame($builder, $result);
    }
}

/**
 * Test model with HasTenant trait
 */
class TestModel extends Model
{
    use HasTenant;

    protected $table = 'test_models';

    public $timestamps = false;

    /**
     * Helper method to test tenant column resolution
     */
    public function getTenantColumnForTest(): string
    {
        return static::resolveTenantColumn($this);
    }
}

/**
 * Test model with custom tenant column
 */
class TestModelWithCustomColumn extends Model
{
    use HasTenant;

    protected $table = 'test_models_custom';

    public $timestamps = false;

    public function getTenantColumn(): string
    {
        return 'organization_id';
    }

    /**
     * Helper method to test tenant column resolution
     */
    public function getTenantColumnForTest(): string
    {
        return static::resolveTenantColumn($this);
    }
}

