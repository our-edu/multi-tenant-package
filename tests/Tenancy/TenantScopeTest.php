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
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Tenancy\TenantScope;
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
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('tenant-uuid-123');

        $this->context
            ->shouldReceive('hasTenant')
            ->andReturn(true);

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions
    }

    public function testApplyScopeDoesNothingWhenNoTenant(): void
    {
        $builder = Mockery::mock(Builder::class);
        $model = Mockery::mock(Model::class);

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
        $model = new class () extends Model {
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
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('account-uuid-456');

        $this->context
            ->shouldReceive('hasTenant')
            ->andReturn(true);

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions
    }

    public function testApplyScopeUsesCustomTenantColumnProperty(): void
    {
        // Create a real test model that defines tenantColumn property
        $model = new class () extends Model {
            protected $table = 'organizations';

            public string $tenantColumn = 'org_id';
        };

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn($model);
        $builder->shouldReceive('where')
            ->with('organizations.org_id', 'org-uuid-789')
            ->once()
            ->andReturnSelf();

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('org-uuid-789');

        $this->context
            ->shouldReceive('hasTenant')
            ->andReturn(true);

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions
    }

    public function testApplyScopeSkipsModelWithWithoutTenantScopeProperty(): void
    {
        // Create a real test model that excludes tenant scope
        $model = new class () extends Model {
            protected $table = 'global_settings';

            public bool $withoutTenantScope = true;
        };

        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('getModel')->andReturn($model);
        $builder->shouldNotReceive('where');

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('tenant-uuid-123');

        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions and where was not called
    }

    public function testApplyScopeSkipsInConsoleWithoutTenant(): void
    {
        // When running in console (which PHPUnit does) and no tenant is set,
        // the scope should skip applying the WHERE clause
        $model = Mockery::mock(Model::class);

        $builder = Mockery::mock(Builder::class);
        $builder->shouldNotReceive('where');

        $this->context
            ->shouldReceive('getTenantId')
            ->once()
            ->andReturn('tenant-uuid-123');

        $this->context
            ->shouldReceive('hasTenant')
            ->once()
            ->andReturn(false);

        // We're already running in console (PHPUnit), so this test will
        // naturally trigger the console detection path
        $this->scope->apply($builder, $model);

        $this->assertTrue(true); // Verify no exceptions and where was not called
    }

    public function testExtendAddsMacros(): void
    {
        $builder = Mockery::mock(Builder::class);

        $builder->shouldReceive('macro')
            ->with('withoutTenantScope', Mockery::type('callable'))
            ->once();

        $builder->shouldReceive('macro')
            ->with('forTenant', Mockery::type('callable'))
            ->once();

        $this->scope->extend($builder);

        $this->assertTrue(true); // Verify macros were registered
    }
}
