<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Tenancy;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use Oured\MultiTenant\Tenancy\TenantContext;
use Oured\MultiTenant\Tenancy\TenantScope;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    private TenantScope $scope;
    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Mockery::mock(TenantContext::class);
        $this->app->bind(TenantContext::class, fn () => $this->context);

        $this->scope = new TenantScope();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function testApplyScopeAddsWhereClauseWhenTenantExists(): void
    {
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);

        $model->shouldReceive('getTable')->andReturn('users');
        $builder->shouldReceive('getModel')->andReturn($model);

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(true);

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('tenant-uuid-123');

        $builder
            ->shouldReceive('where')
            ->with('users.tenant_id', 'tenant-uuid-123')
            ->once()
            ->andReturnSelf();

        $this->scope->apply($builder, $model);

        $builder->shouldHaveReceived('where')->once();
    }

    public function testApplyScopeDoesNothingWhenNoTenant(): void
    {
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(false);

        $this->context
            ->shouldNotReceive('getTenantId');

        $builder
            ->shouldNotReceive('where');

        $this->scope->apply($builder, $model);
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

        $builder
            ->shouldNotReceive('where');

        $this->scope->apply($builder, $model);
    }

    public function testApplyScopeUsesCustomTenantColumn(): void
    {
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);

        $model->shouldReceive('getTable')->andReturn('accounts');
        $model->shouldReceive('getTenantColumn')->andReturn('account_id');
        $builder->shouldReceive('getModel')->andReturn($model);

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(true);

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('account-uuid-456');

        $builder
            ->shouldReceive('where')
            ->with('accounts.account_id', 'account-uuid-456')
            ->once()
            ->andReturnSelf();

        $this->scope->apply($builder, $model);

        $builder->shouldHaveReceived('where')->once();
    }

    public function testForTenantMethod(): void
    {
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);

        $model->shouldReceive('getTable')->andReturn('users');
        $builder->shouldReceive('getModel')->andReturn($model);

        $builder
            ->shouldReceive('where')
            ->with('users.tenant_id', 'specific-tenant-id')
            ->once()
            ->andReturnSelf();

        $result = $this->scope->forTenant($builder, 'specific-tenant-id');

        $this->assertSame($builder, $result);
        $builder->shouldHaveReceived('where')->once();
    }
}

