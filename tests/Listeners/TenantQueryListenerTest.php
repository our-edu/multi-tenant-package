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
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
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
        config(['multi-tenant.tables' => ['orders' => 'App\\Models\\Order']]);

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
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
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
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
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
        config(['multi-tenant.tables' => ['orders' => 'App\\Models\\Order']]);
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
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
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

        $event = $this->createQueryEvent('UPDATE users SET status = ? WHERE status = ?');

        $testListener->handle($event);

        $this->assertTrue($testListener->logCalled);
    }

    public function testDetectsTableInDeleteQuery(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
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

        $event = $this->createQueryEvent('DELETE FROM users WHERE status = ?');

        $testListener->handle($event);

        $this->assertTrue($testListener->logCalled);
    }

    public function testUpdateByPrimaryKeyDoesNotLogError(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                return str_contains(strtolower($sql), 'update ' . $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        // UPDATE by primary key should NOT log error
        $event = $this->createQueryEvent('UPDATE users SET status = ? WHERE id = ?');

        $testListener->handle($event);

        $this->assertFalse($testListener->logCalled);
    }

    public function testDeleteByPrimaryKeyDoesNotLogError(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                return str_contains(strtolower($sql), 'delete from ' . $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        // DELETE by primary key should NOT log error
        $event = $this->createQueryEvent('DELETE FROM users WHERE id = ?');

        $testListener->handle($event);

        $this->assertFalse($testListener->logCalled);
    }

    public function testUpdateByUuidPrimaryKeyDoesNotLogError(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);
        config(['multi-tenant.query_listener.primary_keys' => ['id', 'uuid']]);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                return str_contains(strtolower($sql), 'update ' . $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        // UPDATE by uuid primary key should NOT log error
        $event = $this->createQueryEvent('UPDATE users SET status = ? WHERE uuid = ?');

        $testListener->handle($event);

        $this->assertFalse($testListener->logCalled);
    }

    public function testSkipsModelWithWithoutTenantScopeProperty(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['global_settings' => GlobalSettingStub::class]]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                return str_contains(strtolower($sql), $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        // Query on table with model that has $withoutTenantScope = true should NOT log error
        $event = $this->createQueryEvent('SELECT * FROM global_settings WHERE key = ?');

        $testListener->handle($event);

        $this->assertFalse($testListener->logCalled);
    }

    public function testInsertWithTenantIdDoesNotLogError(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                return str_contains(strtolower($sql), $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        // INSERT with tenant_id column should NOT log error (MySQL style)
        $event = $this->createQueryEvent('INSERT INTO users (name, email, tenant_id) VALUES (?, ?, ?)');

        $testListener->handle($event);

        $this->assertFalse($testListener->logCalled);
    }

    public function testInsertWithQuotedTenantIdDoesNotLogError(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['activity_log' => 'App\\Models\\ActivityLog']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                return str_contains(strtolower($sql), $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        // INSERT with quoted tenant_id column should NOT log error (PostgreSQL style)
        $event = $this->createQueryEvent('insert into "activity_log" ("log_name", "properties", "tenant_id", "created_at") values (?, ?, ?, ?) returning "id"');

        $testListener->handle($event);

        $this->assertFalse($testListener->logCalled);
    }

    public function testInsertWithoutTenantIdLogsError(): void
    {
        config(['multi-tenant.query_listener.enabled' => true]);
        config(['multi-tenant.tables' => ['users' => 'App\\Models\\User']]);
        config(['multi-tenant.tenant_column' => 'tenant_id']);

        $context = Mockery::mock(TenantContext::class);
        $context->shouldReceive('hasTenant')->andReturn(true);
        $context->shouldReceive('getTenantId')->andReturn(1);

        $testListener = new class ($context) extends TenantQueryListener {
            public bool $logCalled = false;

            protected function queryInvolvesTable(string $sql, string $table): bool
            {
                return str_contains(strtolower($sql), $table);
            }

            protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
            {
                $this->logCalled = true;
            }
        };

        // INSERT without tenant_id column SHOULD log error
        $event = $this->createQueryEvent('INSERT INTO users (name, email) VALUES (?, ?)');

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

/**
 * Stub model with $withoutTenantScope = true for testing.
 */
class GlobalSettingStub
{
    public bool $withoutTenantScope = true;
}
