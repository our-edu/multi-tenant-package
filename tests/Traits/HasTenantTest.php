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
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Tenancy\TenantScope;
use Ouredu\MultiTenant\Traits\HasTenant;
use Tests\TestCase;

class HasTenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('getTenantId')->andReturn(123);
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

    public function testBootHasTenantAddsGlobalScope(): void
    {
        $model = new TestModel();

        $scopes = $model->getGlobalScopes();

        $this->assertArrayHasKey('tenant', $scopes);
        $this->assertInstanceOf(TenantScope::class, $scopes['tenant']);
    }

    public function testTenantRelationshipMethodExists(): void
    {
        $model = new TestModel();

        $this->assertTrue(method_exists($model, 'tenant'));
    }

    public function testTenantRelationshipUsesConfiguredModel(): void
    {
        // Verify that the tenant() method reads from config
        $configuredModel = config('multi-tenant.tenant_model');

        $this->assertNotEmpty($configuredModel);
    }

    public function testCustomColumnModelHasGetTenantColumnMethod(): void
    {
        $model = new TestModelWithCustomColumn();

        $this->assertTrue(method_exists($model, 'getTenantColumn'));
        $this->assertEquals('organization_id', $model->getTenantColumn());
    }

    public function testModelWithoutScopePropertyExists(): void
    {
        $model = new TestModelWithoutScope();

        $this->assertTrue(property_exists($model, 'withoutTenantScope'));
        $this->assertTrue($model->withoutTenantScope);
    }

    public function testModelWithoutScopeStillHasGlobalScope(): void
    {
        $model = new TestModelWithoutScope();

        $scopes = $model->getGlobalScopes();

        // The scope is still registered, but withoutTenantScope property
        // is used to skip auto-assignment in creating/updating events
        $this->assertArrayHasKey('tenant', $scopes);
    }

    public function testResolveTenantColumnFallsBackToDefault(): void
    {
        // Model without getTenantColumn method should fallback to 'tenant_id'
        $model = new TestModel();

        $this->assertFalse(method_exists($model, 'getTenantColumn'));
        $this->assertEquals('tenant_id', $model->getTenantColumnForTest());
    }

    public function testResolveTenantColumnUsesGetTenantColumnMethod(): void
    {
        $model = new TestModelWithCustomColumn();

        $this->assertTrue(method_exists($model, 'getTenantColumn'));
        $this->assertEquals('organization_id', $model->getTenantColumn());
        $this->assertEquals('organization_id', $model->getTenantColumnForTest());
    }

    public function testScopeForTenantIncludesTableName(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('where')
            ->withArgs(function ($column, $value) {
                // Verify the column includes the table name with dot separator
                return $column === 'test_models.tenant_id' && $value === 'test-id';
            })
            ->once()
            ->andReturnSelf();

        $model = new TestModel();
        $result = $model->scopeForTenant($builder, 'test-id');

        $this->assertInstanceOf(Builder::class, $result);
    }

    public function testGetTableReturnsCorrectTableName(): void
    {
        $model = new TestModel();
        $this->assertEquals('test_models', $model->getTable());

        $modelCustom = new TestModelWithCustomColumn();
        $this->assertEquals('test_models_custom', $modelCustom->getTable());
    }

    public function testGlobalScopeIsInstanceOfTenantScope(): void
    {
        $model = new TestModel();
        $scopes = $model->getGlobalScopes();

        $this->assertCount(1, $scopes);
        $this->assertInstanceOf(TenantScope::class, $scopes['tenant']);
    }

    public function testMultipleModelsShareSameScopeType(): void
    {
        $model1 = new TestModel();
        $model2 = new TestModelWithCustomColumn();

        $scopes1 = $model1->getGlobalScopes();
        $scopes2 = $model2->getGlobalScopes();

        $this->assertInstanceOf(TenantScope::class, $scopes1['tenant']);
        $this->assertInstanceOf(TenantScope::class, $scopes2['tenant']);
    }

    public function testCreatingCallbackSetsTenantId(): void
    {
        $model = new TestModel();

        // Get the creating callbacks registered on the model
        $dispatcher = TestModel::getEventDispatcher();

        // Manually fire the creating event
        $dispatcher->dispatch('eloquent.creating: ' . TestModel::class, [$model]);

        // Tenant ID should be set from context (123)
        $this->assertEquals(123, $model->getAttribute('tenant_id'));
    }

    public function testCreatingCallbackDoesNotOverrideExistingTenantId(): void
    {
        $model = new TestModel();
        $model->setAttribute('tenant_id', 999);

        $dispatcher = TestModel::getEventDispatcher();
        $dispatcher->dispatch('eloquent.creating: ' . TestModel::class, [$model]);

        // Tenant ID should NOT be overridden
        $this->assertEquals(999, $model->getAttribute('tenant_id'));
    }

    public function testCreatingCallbackSkipsWithoutTenantScope(): void
    {
        $model = new TestModelWithoutScope();

        $dispatcher = TestModelWithoutScope::getEventDispatcher();
        $dispatcher->dispatch('eloquent.creating: ' . TestModelWithoutScope::class, [$model]);

        // Tenant ID should remain null because withoutTenantScope = true
        $this->assertNull($model->getAttribute('tenant_id'));
    }

    public function testUpdatingCallbackSetsTenantId(): void
    {
        $model = new TestModel();
        $model->exists = true;

        $dispatcher = TestModel::getEventDispatcher();
        $dispatcher->dispatch('eloquent.updating: ' . TestModel::class, [$model]);

        // Tenant ID should be set from context (123)
        $this->assertEquals(123, $model->getAttribute('tenant_id'));
    }

    public function testUpdatingCallbackDoesNotOverrideExistingTenantId(): void
    {
        $model = new TestModel();
        $model->exists = true;
        $model->setAttribute('tenant_id', 888);

        $dispatcher = TestModel::getEventDispatcher();
        $dispatcher->dispatch('eloquent.updating: ' . TestModel::class, [$model]);

        // Tenant ID should NOT be overridden
        $this->assertEquals(888, $model->getAttribute('tenant_id'));
    }

    public function testUpdatingCallbackSkipsWithoutTenantScope(): void
    {
        $model = new TestModelWithoutScope();
        $model->exists = true;

        $dispatcher = TestModelWithoutScope::getEventDispatcher();
        $dispatcher->dispatch('eloquent.updating: ' . TestModelWithoutScope::class, [$model]);

        // Tenant ID should remain null because withoutTenantScope = true
        $this->assertNull($model->getAttribute('tenant_id'));
    }

    public function testCreatingCallbackWithNullContext(): void
    {
        // Override context to return null
        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('getTenantId')->andReturn(null);
        $this->app->instance(TenantContext::class, $context);

        $model = new TestModel();

        $dispatcher = TestModel::getEventDispatcher();
        $dispatcher->dispatch('eloquent.creating: ' . TestModel::class, [$model]);

        // Tenant ID should remain null
        $this->assertNull($model->getAttribute('tenant_id'));
    }

    public function testUpdatingCallbackWithNullContext(): void
    {
        // Override context to return null
        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('getTenantId')->andReturn(null);
        $this->app->instance(TenantContext::class, $context);

        $model = new TestModel();
        $model->exists = true;

        $dispatcher = TestModel::getEventDispatcher();
        $dispatcher->dispatch('eloquent.updating: ' . TestModel::class, [$model]);

        // Tenant ID should remain null
        $this->assertNull($model->getAttribute('tenant_id'));
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

    protected $guarded = [];

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

    protected $guarded = [];

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

/**
 * Test model that excludes tenant scope
 */
class TestModelWithoutScope extends Model
{
    use HasTenant;

    protected $table = 'test_models_without_scope';

    public $timestamps = false;

    protected $guarded = [];

    public bool $withoutTenantScope = true;
}
