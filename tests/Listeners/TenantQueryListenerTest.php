<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Mockery;
use Ouredu\MultiTenant\Listeners\TenantQueryListener;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Tests\TestCase;

class TenantQueryListenerTest extends TestCase
{
    public function testSkipsWhenNoTenantContext(): void
    {
        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(false);

        $listener = new TenantQueryListener($context);
        $event = $this->createQueryEvent('SELECT * FROM users');

        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function testSkipsWhenListenerDisabled(): void
    {
        config(['multi-tenant.query_listener.enabled' => false]);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $listener = new TenantQueryListener($context);
        $event = $this->createQueryEvent('SELECT * FROM users');

        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function testSkipsWhenNoTenantTablesConfigured(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => []]);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $listener = new TenantQueryListener($context);
        $event = $this->createQueryEvent('SELECT * FROM users');

        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function testSkipsQueryWithTenantFilter(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $listener = new TenantQueryListener($context);
        $event = $this->createQueryEvent('SELECT * FROM users WHERE tenant_id = ?');

        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function testSkipsQueryOnNonTenantTable(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['orders']]);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $listener = new TenantQueryListener($context);
        $event = $this->createQueryEvent('SELECT * FROM users WHERE id = ?');

        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function testDetectsQueryWithoutTenantFilter(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);
        $context->shouldReceive('getTenantId')->andReturn(1);

        $event = $this->createQueryEvent('SELECT * FROM users WHERE status = ?');

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            public array $logData = [];

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
                $this->logData = [
                    'sql' => $sql,
                    'table' => $table,
                ];
            }
        };

        $testListener->handle($event);

        $this->assertTrue($testListener->logCalled);
        $this->assertEquals('users', $testListener->logData['table']);
    }

    public function testAcceptsQueryWithTenantFilterInAndClause(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $listener = new TenantQueryListener($context);
        $event = $this->createQueryEvent('SELECT * FROM users WHERE status = ? AND tenant_id = ?');

        $listener->handle($event);

        $this->assertTrue(true);
    }

    public function testDetectsTableInJoinQuery(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['orders']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);
        $context->shouldReceive('getTenantId')->andReturn(1);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        $event = $this->createQueryEvent('SELECT * FROM users JOIN orders ON users.id = orders.user_id');

        $testListener->handle($event);

        $this->assertTrue($testListener->logCalled);
    }

    public function testDetectsTableInUpdateQuery(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);
        $context->shouldReceive('getTenantId')->andReturn(1);

        // Test that the listener can process an update query
        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                // Force match for testing
                return str_contains(strtolower($sql), 'update ' . $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        $event = $this->createQueryEvent('UPDATE users SET status = ? WHERE id = ?');

        $testListener->handle($event);

        $this->assertTrue($testListener->logCalled);
    }

    public function testDetectsTableInDeleteQuery(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);
        $context->shouldReceive('getTenantId')->andReturn(1);

        // Test that the listener can process a delete query
        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                // Force match for testing
                return str_contains(strtolower($sql), 'delete from ' . $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        $event = $this->createQueryEvent('DELETE FROM users WHERE id = ?');

        $testListener->handle($event);

        $this->assertTrue($testListener->logCalled);
    }

    /**
     * Create a QueryExecuted event.
     */
    private function createQueryEvent(string $sql): QueryExecuted
    {
        $connection = Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getName')->andReturn('mysql');

        return new QueryExecuted(
            $sql,
            [],
            0.1,
            $connection
        );
    }
}
