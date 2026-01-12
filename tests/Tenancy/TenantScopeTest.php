<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use Mockery\MockInterface;
use Oured\MultiTenant\Contracts\TenantResolver;
use Oured\MultiTenant\Tenancy\TenantContext;
use Oured\MultiTenant\Tenancy\TenantScope;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    private TenantScope $scope;
    private TenantContext|MockInterface $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Mockery::mock(TenantContext::class);
        $this->app->instance(TenantContext::class, $this->context);

        $this->scope = new TenantScope();
    }

    public function testApplyScopeAddsWhereClauseWhenTenantExists(): void
    {
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('users');

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn($model);
        $builder->shouldReceive('where')
            ->with('users.tenant_id', 'tenant-uuid-123')
            ->once()
            ->andReturnSelf();

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(true);

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('tenant-uuid-123');

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions
    }

    public function testApplyScopeDoesNothingWhenNoTenant(): void
    {
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(false);

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions
    }

    public function testApplyScopeDoesNothingWhenTenantIdIsNull(): void
    {
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(true);

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturnNull();

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions
    }

    public function testApplyScopeUsesCustomTenantColumn(): void
    {
        // Create a real test model that defines getTenantColumn
        $model = new class extends Model {
            protected $table = 'accounts';

            public function getTenantColumn(): string
            {
                return 'account_id';
            }
        };

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn($model);
        $builder->shouldReceive('where')
            ->with('accounts.account_id', 'account-uuid-456')
            ->once()
            ->andReturnSelf();

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(true);

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('account-uuid-456');

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions
    }

    public function testForTenantMethod(): void
    {
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('getTable')->andReturn('users');

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn($model);
        $builder->shouldReceive('where')
            ->with('users.tenant_id', 'specific-tenant-id')
            ->once()
            ->andReturnSelf();

        $result = $this->scope->forTenant($builder, 'specific-tenant-id');

        $this->assertSame($builder, $result);
    }
}

