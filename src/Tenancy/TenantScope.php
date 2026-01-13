<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope
 *
 * Global scope that adds `WHERE tenant_id = ?` (or a custom tenant column)
 * to all queries for models that use it.
 *
 * This is intentionally generic:
 * - Column name is resolved from the model if it defines getTenantColumn()
 * - Otherwise defaults to 'tenant_id'
 *
 * Features:
 * - Automatic tenant filtering on all queries
 * - Support for models without tenant scope via $withoutTenantScope property
 * - Query macros for bypassing scope: withoutTenantScope(), forTenant($tenantId)
 * - Console command detection to skip scope when no tenant is set
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder $builder
     * @param Model $model
     * @return void
     */
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        $tenantId = $context->getTenantId();

        if ($tenantId && $this->shouldApplyScope($model)) {
            $column = $this->getTenantColumn($model);
            $builder->where($builder->getModel()->getTable() . '.' . $column, $tenantId);
        }
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param Builder $builder
     * @return void
     */
    public function extend(Builder $builder): void
    {
        // Add method to query without tenant scope
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(TenantScope::class);
        });

        // Add method to query with specific tenant
        $builder->macro('forTenant', function (Builder $builder, string $tenantId) {
            $model = $builder->getModel();
            $column = $this->getTenantColumn($model);

            return $builder->withoutGlobalScope(TenantScope::class)
                ->where($model->getTable() . '.' . $column, $tenantId);
        });
    }

    /**
     * Get the tenant column name for the model.
     *
     * @param Model $model
     * @return string
     */
    private function getTenantColumn(Model $model): string
    {
        // Custom hook on the model: public function getTenantColumn(): string
        if (method_exists($model, 'getTenantColumn')) {
            return $model->getTenantColumn();
        }

        // Check if model has custom tenant column property
        if (property_exists($model, 'tenantColumn')) {
            return $model->tenantColumn;
        }

        // Default column name
        return 'tenant_id';
    }

    /**
     * Determine if the scope should be applied.
     *
     * @param Model $model
     * @return bool
     */
    private function shouldApplyScope(Model $model): bool
    {
        // Skip if model explicitly excludes tenant scope
        if (property_exists($model, 'withoutTenantScope') && $model->withoutTenantScope) {
            return false;
        }

        // Skip if running in console and no tenant is set
        if (app()->runningInConsole() && ! app(TenantContext::class)->hasTenant()) {
            return false;
        }

        return true;
    }
}
