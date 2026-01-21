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
     * Check if the query has a tenant_id filter or is a safe primary key operation.
     *
     * UPDATE/DELETE queries by primary key (id) are considered safe because
     * the model was already loaded with tenant scope applied.
     */
    protected function queryHasTenantFilter(string $sql): bool
    {
        $tenantColumn = $this->getTenantColumn();

        // Check for tenant_id in WHERE clause
        $tenantPatterns = [
            '/\bwhere\b.*\b' . preg_quote($tenantColumn, '/') . '\b/i',
            '/\band\b.*\b' . preg_quote($tenantColumn, '/') . '\b/i',
            '/\b' . preg_quote($tenantColumn, '/') . '\s*=/i',
        ];

        foreach ($tenantPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }

        // Check if this is an UPDATE/DELETE by primary key (id)
        // These are safe because the model was loaded with tenant scope
        if ($this->isUpdateOrDeleteByPrimaryKey($sql)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the query is an UPDATE or DELETE by primary key.
     *
     * When Eloquent updates/deletes a model, it uses WHERE id = ? or WHERE uuid = ?
     * The model was already loaded with tenant scope, so this is safe.
     */
    protected function isUpdateOrDeleteByPrimaryKey(string $sql): bool
    {
        // Common primary key column names
        $primaryKeys = $this->getPrimaryKeyColumns();

        foreach ($primaryKeys as $pk) {
            $patterns = [
                '/\bupdate\b.+\bwhere\b\s+[`"\']?' . preg_quote($pk, '/') . '[`"\']?\s*=\s*\?/i',
                '/\bdelete\b.+\bwhere\b\s+[`"\']?' . preg_quote($pk, '/') . '[`"\']?\s*=\s*\?/i',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $sql)) {
                    return true;
                }
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
    protected function logMissingTenantFilter(string $sql, string $table, QueryExecuted $event): void
    {
        $channel = config('multi-tenant.query_listener.log_channel');
        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $source = $this->getQuerySource();

        $logger->error('Query executed without tenant_id filter', [
            'table' => $table,
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
