<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Validation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Validation\DatabasePresenceVerifier;
use Ouredu\MultiTenant\Tenancy\TenantContext;

/**
 * Adds tenant-aware filtering for Laravel database validation rules
 * like exists and unique.
 */
class TenantDatabasePresenceVerifier extends DatabasePresenceVerifier
{
    public function __construct(
        ConnectionResolverInterface $db,
        private readonly Application $app
    ) {
        parent::__construct($db);
    }

    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = []): int
    {
        $extra = $this->appendTenantCondition((string) $collection, $extra);

        return parent::getCount($collection, $column, $value, $excludeId, $idColumn, $extra);
    }

    public function getMultiCount($collection, $column, array $values, array $extra = []): int
    {
        $extra = $this->appendTenantCondition((string) $collection, $extra);

        return parent::getMultiCount($collection, $column, $values, $extra);
    }

    /**
     * @param array<mixed> $extra
     * @return array<mixed>
     */
    protected function appendTenantCondition(string $table, array $extra): array
    {
        /** @var TenantContext $context */
        $context = $this->app->make(TenantContext::class);
        $tenantId = $context->getTenantId();

        if (! $this->shouldScopeValidationToTenant($table, $tenantId)) {
            return $extra;
        }

        $tenantColumn = (string) config('multi-tenant.tenant_column', 'tenant_id');

        if ($this->extraContainsTenantCondition($extra, $tenantColumn)) {
            return $extra;
        }

        $extra[$tenantColumn] = $tenantId;

        return $extra;
    }

    protected function shouldScopeValidationToTenant(string $table, ?int $tenantId): bool
    {
        if (! (bool) config('multi-tenant.validation.apply_tenant_scope', true)) {
            return false;
        }

        if ($tenantId === null) {
            return false;
        }

        $tables = $this->getScopedValidationTables();

        if (empty($tables) || in_array('*', $tables, true)) {
            return true;
        }

        $normalizedTable = $this->normalizeTableName($table);

        foreach ($tables as $configuredTable) {
            if (! is_string($configuredTable) || $configuredTable === '') {
                continue;
            }

            if ($this->normalizeTableName($configuredTable) === $normalizedTable) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function getScopedValidationTables(): array
    {
        $validationTables = config('multi-tenant.tables', []);

        if (is_array($validationTables) && ! empty($validationTables)) {
            return array_keys($validationTables);
        }

        return ['*'];
    }

    /**
     * @param array<mixed> $extra
     */
    protected function extraContainsTenantCondition(array $extra, string $tenantColumn): bool
    {
        $normalizedTenantColumn = $this->normalizeColumnName($tenantColumn);

        foreach ($extra as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = $this->normalizeColumnName($key);

            if ($normalizedKey === $normalizedTenantColumn || str_ends_with($normalizedKey, '.' . $normalizedTenantColumn)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeTableName(string $table): string
    {
        $table = strtolower(trim($table));

        $table = str_replace(['`', '"'], '', $table);

        $segments = explode('.', $table);

        return end($segments) ?: $table;
    }

    protected function normalizeColumnName(string $column): string
    {
        $column = strtolower(trim($column));

        return str_replace(['`', '"'], '', $column);
    }
}
