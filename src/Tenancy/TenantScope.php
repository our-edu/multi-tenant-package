<?php

declare(strict_types=1);

namespace Oured\MultiTenant\Tenancy;

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
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        if (! $context->hasTenant()) {
            return;
        }

        $tenantId = $context->getTenantId();

        if (! $tenantId) {
            return;
        }

        $column = $this->getTenantColumn($model);

        $builder->where($builder->getModel()->getTable().'.'.$column, $tenantId);
    }

    /**
     * Allow querying for a specific tenant explicitly.
     */
    public function forTenant(Builder $builder, string $tenantId): Builder
    {
        $model  = $builder->getModel();
        $column = $this->getTenantColumn($model);

        return $builder->where($model->getTable().'.'.$column, $tenantId);
    }

    private function getTenantColumn(Model $model): string
    {
        // Custom hook on the model: public function getTenantColumn(): string
        if (method_exists($model, 'getTenantColumn')) {
            return $model->getTenantColumn();
        }

        // Default column name
        return 'tenant_id';
    }
}


