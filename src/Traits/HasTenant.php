<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Oured\MultiTenant\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Oured\MultiTenant\Tenancy\TenantContext;
use Oured\MultiTenant\Tenancy\TenantScope;

/**
 * HasTenant
 *
 * Trait for Eloquent models that belong to a tenant.
 *
 * Features:
 * - `tenant()` relationship
 * - `scopeForTenant()` query scope
 * - Automatic tenant assignment on create/update
 * - Global TenantScope registration when used on a model
 */
trait HasTenant
{
    /**
     * Boot the HasTenant trait for a model.
     */
    public static function bootHasTenant(): void
    {
        static::addGlobalScope('tenant', new TenantScope());

        // Set tenant on creation if missing
        static::creating(function (Model $model): void {
            $column = static::resolveTenantColumn($model);

            if (! $model->getAttribute($column)) {
                /** @var TenantContext $context */
                $context  = app(TenantContext::class);
                $tenantId = $context->getTenantId();

                if ($tenantId) {
                    $model->setAttribute($column, $tenantId);
                }
            }
        });

        // Set tenant on update if still missing
        static::updating(function (Model $model): void {
            $column = static::resolveTenantColumn($model);

            if (! $model->getAttribute($column)) {
                /** @var TenantContext $context */
                $context  = app(TenantContext::class);
                $tenantId = $context->getTenantId();

                if ($tenantId) {
                    $model->setAttribute($column, $tenantId);
                }
            }
        });
    }

    /**
     * Relationship to the tenant model.
     *
     * The host application can decide what the Tenant model class is by
     * configuring it in `config/multi-tenant.php` (see service provider).
     */
    public function tenant(): BelongsTo
    {
        /** @var class-string<Model> $tenantModel */
        $tenantModel = config('multi-tenant.tenant_model');

        $column = static::resolveTenantColumn($this);

        return $this->belongsTo($tenantModel, $column);
    }

    /**
     * Scope a query to only include records for the given tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        $column = static::resolveTenantColumn($this);

        return $query->where($this->getTable().'.'.$column, $tenantId);
    }

    /**
     * Resolve the tenant column for the given model.
     */
    protected static function resolveTenantColumn(Model $model): string
    {
        // If model defines a custom tenant column method, use it
        if (method_exists($model, 'getTenantColumn')) {
            return $model->getTenantColumn();
        }

        // Otherwise fallback to 'tenant_id'
        return 'tenant_id';
    }
}


