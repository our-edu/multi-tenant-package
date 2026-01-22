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
use ReflectionClass;

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
        $tablesConfig = $this->getTenantTablesConfig();

        // Check if query involves tenant tables
        foreach ($tablesConfig as $table => $modelClass) {
            // Skip if model is excluded from tenant scope
            if ($this->isModelExcludedFromTenantScope($modelClass)) {
                continue;
            }

            $operation = $this->queryInvolvesTable($sql, $table);

            if ($operation !== null && ! $this->queryHasTenantFilter($sql, $operation)) {
                $this->logMissingTenantFilter($sql, $table, $operation, $event);

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
     * Get the tenant tables configuration (table => model class).
     *
     * @return array<string, string>
     */
    protected function getTenantTablesConfig(): array
    {
        return config('multi-tenant.tables', []);
    }

    /**
     * Check if a model is excluded from tenant scope.
     */
    protected function isModelExcludedFromTenantScope(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        // Check if model has $withoutTenantScope property set to true
        if (property_exists($modelClass, 'withoutTenantScope')) {
            $defaults = (new ReflectionClass($modelClass))->getDefaultProperties();

            return $defaults['withoutTenantScope'] ?? false;
        }

        return false;
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
     *
     * @return string|null Returns the operation type (select, insert, update, delete) or null if not involved
     */
    protected function queryInvolvesTable(string $sql, string $table): ?string
    {
        $quotedTable = preg_quote($table, '/');

        // INSERT: INSERT INTO table
        if (preg_match('/\binsert\s+into\s+[`"\']?' . $quotedTable . '[`"\']?(?:\s|\()/i', $sql)) {
            return 'insert';
        }

        // UPDATE: UPDATE table
        if (preg_match('/\bupdate\s+[`"\']?' . $quotedTable . '[`"\']?(?:\s|$)/i', $sql)) {
            return 'update';
        }

        // DELETE: DELETE FROM table (must check before SELECT because DELETE FROM contains FROM)
        if (preg_match('/\bdelete\s+from\s+[`"\']?' . $quotedTable . '[`"\']?(?:\s|$)/i', $sql)) {
            return 'delete';
        }

        // SELECT: FROM table, JOIN table
        $selectPatterns = [
            '/\bfrom\s+[`"\']?' . $quotedTable . '[`"\']?(?:\s|$|,)/i',
            '/\bjoin\s+[`"\']?' . $quotedTable . '[`"\']?(?:\s|$)/i',
        ];

        foreach ($selectPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return 'select';
            }
        }

        return null;
    }

    /**
     * Check if the query has a tenant_id filter based on the operation type.
     *
     * @param string $sql The SQL query
     * @param string $operation The operation type (select, insert, update, delete)
     * @return bool True if the query properly includes tenant_id
     */
    protected function queryHasTenantFilter(string $sql, string $operation): bool
    {
        $tenantColumn = $this->getTenantColumn();

        return match ($operation) {
            'select' => $this->selectHasTenantFilter($sql, $tenantColumn),
            'insert' => $this->insertHasTenantColumn($sql, $tenantColumn),
            'update' => $this->updateHasTenantFilter($sql, $tenantColumn),
            'delete' => $this->deleteHasTenantFilter($sql, $tenantColumn),
            default => false,
        };
    }

    /**
     * Check if SELECT query has tenant_id in WHERE clause.
     */
    protected function selectHasTenantFilter(string $sql, string $tenantColumn): bool
    {
        return $this->hasWhereWithTenantColumn($sql, $tenantColumn);
    }

    /**
     * Check if INSERT query includes tenant_id column.
     */
    protected function insertHasTenantColumn(string $sql, string $tenantColumn): bool
    {
        // Check if tenant column is in the column list
        // Pattern matches both: tenant_id (MySQL) and "tenant_id" (PostgreSQL)
        // Examples:
        //   insert into table (col1, tenant_id, col2) values ...
        //   insert into "table" ("col1", "tenant_id", "col2") values ...
        $quotedTenantColumn = preg_quote($tenantColumn, '/');

        // Match tenant column in the column list (before VALUES)
        $pattern = '/\binsert\s+into\s+[`"\']?\w+[`"\']?\s*\([^)]*[`"\']?' . $quotedTenantColumn . '[`"\']?[^)]*\)/i';

        return (bool) preg_match($pattern, $sql);
    }

    /**
     * Check if UPDATE query has tenant_id in WHERE clause or is by primary key.
     */
    protected function updateHasTenantFilter(string $sql, string $tenantColumn): bool
    {
        // First check if WHERE clause contains tenant_id
        if ($this->hasWhereWithTenantColumn($sql, $tenantColumn)) {
            return true;
        }

        // UPDATE by primary key is safe (model was loaded with tenant scope)
        if ($this->isOperationByPrimaryKey($sql, 'update')) {
            return true;
        }

        return false;
    }

    /**
     * Check if DELETE query has tenant_id in WHERE clause or is by primary key.
     */
    protected function deleteHasTenantFilter(string $sql, string $tenantColumn): bool
    {
        // First check if WHERE clause contains tenant_id
        if ($this->hasWhereWithTenantColumn($sql, $tenantColumn)) {
            return true;
        }

        // DELETE by primary key is safe (model was loaded with tenant scope)
        if ($this->isOperationByPrimaryKey($sql, 'delete')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the SQL has tenant column in WHERE clause.
     */
    protected function hasWhereWithTenantColumn(string $sql, string $tenantColumn): bool
    {
        $quotedColumn = preg_quote($tenantColumn, '/');

        // Patterns for tenant_id in WHERE clause
        // Handles: WHERE tenant_id = ?, WHERE "tenant_id" = ?, AND tenant_id = ?, etc.
        $patterns = [
            // WHERE tenant_id = ? or WHERE "tenant_id" = ?
            '/\bwhere\s+[`"\']?' . $quotedColumn . '[`"\']?\s*=\s*\?/i',
            // WHERE ... AND tenant_id = ?
            '/\bwhere\b.*\band\s+[`"\']?' . $quotedColumn . '[`"\']?\s*=\s*\?/i',
            // WHERE ... tenant_id = ? (anywhere in WHERE)
            '/\bwhere\b[^;]*[`"\']?' . $quotedColumn . '[`"\']?\s*=\s*\?/i',
            // Subquery or complex: just check if tenant_id = ? exists after WHERE
            '/\bwhere\b.*[`"\']?' . $quotedColumn . '[`"\']?\s*(=|in\s*\()/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the operation is by primary key (safe because model was loaded with tenant scope).
     */
    protected function isOperationByPrimaryKey(string $sql, string $operation): bool
    {
        $primaryKeys = $this->getPrimaryKeyColumns();

        foreach ($primaryKeys as $pk) {
            $quotedPk = preg_quote($pk, '/');

            $pattern = match ($operation) {
                'update' => '/\bupdate\b.+?\bwhere\s+[`"\']?' . $quotedPk . '[`"\']?\s*=\s*\?/i',
                'delete' => '/\bdelete\b.+?\bwhere\s+[`"\']?' . $quotedPk . '[`"\']?\s*=\s*\?/i',
                default => null,
            };

            if ($pattern && preg_match($pattern, $sql)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of primary key column names to check.
     *
     * @return array<string>
     */
    protected function getPrimaryKeyColumns(): array
    {
        return config('multi-tenant.query_listener.primary_keys', ['id', 'uuid']);
    }

    /**
     * Log the missing tenant filter error.
     */
    protected function logMissingTenantFilter(string $sql, string $table, string $operation, QueryExecuted $event): void
    {
        $channel = config('multi-tenant.query_listener.log_channel');
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $source = $this->getQuerySource();

        $message = match ($operation) {
            'select' => 'SELECT query executed without tenant_id in WHERE clause',
            'insert' => 'INSERT query executed without tenant_id column',
            'update' => 'UPDATE query executed without tenant_id filter',
            'delete' => 'DELETE query executed without tenant_id filter',
            default => 'Query executed without tenant_id filter',
        };

        $logger->error($message, [
            'table' => $table,
            'operation' => strtoupper($operation),
            'sql' => $sql,
            'bindings' => $event->bindings,
            'time' => $event->time,
            'connection' => $event->connectionName,
            'tenant_id' => $this->context->getTenantId(),
            'file' => $source['file'],
            'line' => $source['line'],
        ]);
    }

    /**
     * Get the source file and line where the query originated.
     */
    protected function getQuerySource(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);

        // Skip framework and package files to find the actual source
        $skipPatterns = [
            '/vendor/',
            '/Illuminate/',
            '/Database/',
            '/TenantQueryListener/',
        ];

        foreach ($trace as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }

            $shouldSkip = false;
            foreach ($skipPatterns as $pattern) {
                if (str_contains($frame['file'], $pattern)) {
                    $shouldSkip = true;

                    break;
                }
            }

            if (! $shouldSkip) {
                return [
                    'file' => $frame['file'],
                    'line' => $frame['line'] ?? 0,
                ];
            }
        }

        return [
            'file' => 'unknown',
            'line' => 0,
        ];
    }
}
