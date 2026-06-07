<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Validation;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Tests\TestCase;

class TenantDatabasePresenceVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('multi-tenant.validation.apply_tenant_scope', true);
        config()->set('multi-tenant.tenant_column', 'tenant_id');
        config()->set('multi-tenant.tables', [
            'users' => 'App\\Models\\User',
        ]);

        Schema::connection('testing')->create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('email')->nullable();
            $table->string('uuid')->nullable();
        });

        DB::connection('testing')->table('users')->insert([
            ['tenant_id' => 1, 'email' => 'tenant1@example.com', 'uuid' => 'tenant-1-uuid'],
            ['tenant_id' => 2, 'email' => 'tenant2@example.com', 'uuid' => 'tenant-2-uuid'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection('testing')->dropIfExists('users');

        parent::tearDown();
    }

    public function testUniqueRuleIsScopedToCurrentTenant(): void
    {
        app(TenantContext::class)->setTenantId(1);

        $failsForCurrentTenant = Validator::make(
            ['email' => 'tenant1@example.com'],
            ['email' => 'unique:users,email']
        );

        $passesForAnotherTenantData = Validator::make(
            ['email' => 'tenant2@example.com'],
            ['email' => 'unique:users,email']
        );

        $this->assertTrue($failsForCurrentTenant->fails());
        $this->assertTrue($passesForAnotherTenantData->passes());
    }

    public function testExistsRuleIsScopedToCurrentTenant(): void
    {
        app(TenantContext::class)->setTenantId(1);

        $passesForCurrentTenant = Validator::make(
            ['uuid' => 'tenant-1-uuid'],
            ['uuid' => 'exists:users,uuid']
        );

        $failsForAnotherTenant = Validator::make(
            ['uuid' => 'tenant-2-uuid'],
            ['uuid' => 'exists:users,uuid']
        );

        $this->assertTrue($passesForCurrentTenant->passes());
        $this->assertTrue($failsForAnotherTenant->fails());
    }

    public function testExplicitTenantConditionInRuleIsRespected(): void
    {
        app(TenantContext::class)->setTenantId(1);

        $validator = Validator::make(
            ['email' => 'tenant2@example.com'],
            ['email' => 'unique:users,email,NULL,id,tenant_id,2']
        );

        $this->assertTrue($validator->fails());
    }
}
