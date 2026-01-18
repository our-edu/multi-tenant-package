<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;
use Ouredu\MultiTenant\Tenancy\TenantContext;

/**
 * TenantQueryListener
 *
 * Listens to all database queries and logs an error when a query
 * is executed on a tenant-scoped table without a tenant_id filter.
 */
class TenantQueryListener
{
    public function __construct(
        private readonly TenantContext $context
    ) {
    }

    /**
     * Handle the QueryExecuted event.
     */
    public function handle(QueryExecuted $event): void
    {
        // Skip if tenant context is not set (console commands, etc.)
        if (! $this->context->hasTenant()) {
            return;
        }

        // Skip if listener is disabled
        if (! $this->isEnabled()) {
            return;
        }

        $sql = $event->sql;
        $tables = $this->getTenantTables();

        // Check if query involves tenant tables
        foreach ($tables as $table) {
            if ($this->queryInvolvesTable($sql, $table) && ! $this->queryHasTenantFilter($sql)) {
                $this->logMissingTenantFilter($sql, $table, $event);

                break;
            }
        }
    }

    /**
     * Check if listener is enabled.
     */
    protected function isEnabled(): bool
    {
        return (bool) config('multi-tenant.query_listener.enabled', true);
    }

    /**
     * Get the list of tenant-scoped tables.
     */
    protected function getTenantTables(): array
    {
        return config('multi-tenant.tables', []);
    }

    /**
     * Get the tenant column name.
     */
    protected function getTenantColumn(): string
    {
        return config('multi-tenant.tenant_column', 'tenant_id');
    }

    /**
     * Check if the query involves a specific table.
     */
    protected function queryInvolvesTable(string $sql, string $table): bool
    {
        $patterns = [
            '/\bfrom\s+[`"\']?' . preg_quote($table, '/') . '[`"\']?\b/i',
            '/\bjoin\s+[`"\']?' . preg_quote($table, '/') . '[`"\']?\b/i',
            '/\binto\s+[`"\']?' . preg_quote($table, '/') . '[`"\']?\b/i',
            '/\bupdate\s+[`"\']?' . preg_quote($table, '/') . '[`"\']?\b/i',
            '/\bdelete\s+from\s+[`"\']?' . preg_quote($table, '/') . '[`"\']?\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the query has a tenant_id filter.
     */
    protected function queryHasTenantFilter(string $sql): bool
    {
        $tenantColumn = $this->getTenantColumn();

        $patterns = [
            '/\bwhere\b.*\b' . preg_quote($tenantColumn, '/') . '\b/i',
            '/\band\b.*\b' . preg_quote($tenantColumn, '/') . '\b/i',
            '/\b' . preg_quote($tenantColumn, '/') . '\s*=/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log the missing tenant filter error.
     */
    protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
    {
        $channel = config('multi-tenant.query_listener.log_channel');
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->error('Query executed without tenant_id filter', [
            'table' => $table,
            'sql' => $sql,
            'bindings' => $event->bindings,
            'time' => $event->time,
            'connection' => $event->connectionName,
            'tenant_id' => $this->context->getTenantId(),
        ]);
    }
}
