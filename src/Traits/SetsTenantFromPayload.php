<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Traits;

use Illuminate\Support\Facades\Log;
use Ouredu\MultiTenant\Exceptions\TenantNotFoundException;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Throwable;

/**
 * SetsTenantFromPayload
 *
 * Trait for event/message listeners that need to set tenant context
 * from the message payload. Use this trait in all listeners to ensure
 * tenant-aware queries work correctly.
 *
 * @example
 * class PaymentCreatedListener
 * {
 *     use SetsTenantFromPayload;
 *
 *     public function handle(PaymentCreatedEvent $event): void
 *     {
 *         $this->setTenantFromPayload($event->payload);
 *
 *         // Now all queries will be tenant-scoped
 *         $order = Order::find($event->orderId);
 *     }
 * }
 */
trait SetsTenantFromPayload
{
    /**
     * Set tenant context from payload.
     *
     * First checks if tenant_id exists in payload. If not found and
     * fallback_to_database is enabled, queries for an active tenant.
     * Otherwise throws TenantNotFoundException.
     *
     * @param array<string, mixed>|object $payload The message payload
     * @throws TenantNotFoundException When tenant cannot be resolved
     */
    public function setTenantFromPayload(array|object $payload): void
    {
        $context = app(TenantContext::class);
        $tenantColumn = config('multi-tenant.tenant_column', 'tenant_id');

        // Try to get tenant_id from payload
        $tenantId = $this->extractTenantIdFromPayload($payload, $tenantColumn);

        if ($tenantId !== null) {
            $context->setTenantId($tenantId);

            return;
        }

        // Check fallback configuration
        if ($this->shouldFallbackToDatabase()) {
            $tenantId = $this->getActiveTenantFromDatabase();

            if ($tenantId !== null) {
                $context->setTenantId($tenantId);

                return;
            }
        }

        // No tenant found - throw exception
        throw new TenantNotFoundException(
            'Tenant ID not found in payload and fallback is disabled or no active tenant exists.'
        );
    }

    /**
     * Extract tenant_id from payload (array or object).
     *
     * @param array<string, mixed>|object $payload
     * @param string $tenantColumn
     * @return int|null
     */
    protected function extractTenantIdFromPayload(array|object $payload, string $tenantColumn): ?int
    {
        if (is_array($payload)) {
            $value = $payload[$tenantColumn] ?? null;
        } else {
            $value = $payload->{$tenantColumn} ?? null;
        }

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Check if fallback to database is enabled.
     */
    protected function shouldFallbackToDatabase(): bool
    {
        return (bool) config('multi-tenant.listener.fallback_to_database', false);
    }

    /**
     * Get active tenant ID from database.
     *
     * @return int|null
     */
    protected function getActiveTenantFromDatabase(): ?int
    {
        $tenantModel = config('multi-tenant.tenant_model');

        if (! $tenantModel || ! class_exists($tenantModel)) {
            Log::warning('Multi-tenant: Tenant model not configured or does not exist.', [
                'model' => $tenantModel,
            ]);

            return null;
        }

        try {
            $tenant = $tenantModel::query()
                ->where('is_active', true)
                ->first();

            return $tenant?->id;
        } catch (Throwable $e) {
            Log::error('Multi-tenant: Failed to query active tenant from database.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
