<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Throwable;

/**
 * UserSessionTenantResolver
 *
 * Resolves the current tenant from a shared UserSession/access table that is
 * common across all services, or from an existing getSession() helper if
 * present.
 *
 * Resolution flow:
 * 1. If running in console (and not unit tests) → skip
 * 2. Try getSession() helper (if defined and returns a Model)
 * 3. Otherwise, resolve session from configured session model using a
 *    request header (e.g. X-Session-Id)
 * 4. Read tenant_id (or configured tenant_column) from the session model
 * 5. Use configured tenant_model to resolve and return the tenant
 */
class UserSessionTenantResolver implements TenantResolver
{
    /**
     * Resolve the current tenant model.
     */
    public function resolveTenant(): ?Model
    {
        // Skip resolution in console (except when running tests)
        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return null;
        }

        // Prefer existing helper if available for backwards compatibility
        $session = $this->getSessionFromHelper() ?? $this->getSessionFromAccessTable();

        if (! $session) {
            return null;
        }

        // Try to get tenant from session relationship first (reduces query count)
        $tenant = $this->getTenantFromSessionRelationship($session);

        if ($tenant) {
            return $tenant;
        }

        // Fall back to resolving tenant by ID from session column
        $tenantId = $this->getTenantIdFromSession($session);

        if (! $tenantId) {
            return null;
        }

        // Resolve tenant model
        return $this->resolveTenantById($tenantId);
    }

    /**
     * Try to get tenant from session's relationship if available.
     * This reduces query count when the session model has a tenant relationship.
     */
    protected function getTenantFromSessionRelationship(Model $session): ?Model
    {
        $relationName = $this->getSessionTenantRelation();

        // Check if the session model has the tenant relationship method
        if (! method_exists($session, $relationName)) {
            return null;
        }

        try {
            // Check if tenant is already eager-loaded to avoid extra query
            if ($session->relationLoaded($relationName)) {
                $tenant = $session->getRelation($relationName);

                return $tenant instanceof Model ? $tenant : null;
            }

            // Load the relationship
            $tenant = $session->{$relationName};

            return $tenant instanceof Model ? $tenant : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get session using the getSession() helper function, if available.
     */
    protected function getSessionFromHelper(): ?Model
    {
        if (! function_exists('getSession')) {
            return null;
        }

        try {
            $session = getSession();

            return $session instanceof Model ? $session : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve session from the shared access table using request metadata.
     */
    protected function getSessionFromAccessTable(): ?Model
    {
        $modelClass = $this->getSessionModelClass();

        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        $request = $this->getRequestFromContainer();

        if (! $request) {
            return null;
        }

        $identifier = $this->getSessionIdentifierFromRequest($request);

        if (! $identifier) {
            return null;
        }

        $idColumn = $this->getSessionIdColumn();

        try {
            /** @var Model|null $session */
            $session = $modelClass::where($idColumn, $identifier)->first();

            return $session instanceof Model ? $session : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Extract session identifier from the current request.
     */
    protected function getSessionIdentifierFromRequest(Request $request): ?string
    {
        $header = $this->getSessionIdHeader();

        if (! $header) {
            return null;
        }

        $identifier = $request->headers->get($header);

        return $identifier ? (string) $identifier : null;
    }

    /**
     * Get tenant_id from the session model.
     */
    protected function getTenantIdFromSession(Model $session): ?string
    {
        $tenantColumn = $this->getSessionTenantColumn();

        $tenantId = $session->{$tenantColumn} ?? null;

        return $tenantId ? (string) $tenantId : null;
    }

    /**
     * Resolve tenant model by ID.
     */
    protected function resolveTenantById(string $tenantId): ?Model
    {
        $tenantModel = $this->getTenantModel();

        if (! $tenantModel || ! class_exists($tenantModel)) {
            return null;
        }

        try {
            /** @var Model|null $tenant */
            $tenant = $tenantModel::find($tenantId);

            return $tenant instanceof Model ? $tenant : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the Tenant model class.
     */
    protected function getTenantModel(): ?string
    {
        return config('multi-tenant.tenant_model');
    }

    /**
     * Get the session model class for the shared access table.
     */
    protected function getSessionModelClass(): ?string
    {
        return config('multi-tenant.session.model');
    }

    /**
     * Get the session identifier column name.
     */
    protected function getSessionIdColumn(): string
    {
        return (string) config('multi-tenant.session.id_column', 'id');
    }

    /**
     * Get the HTTP header that carries the session identifier.
     */
    protected function getSessionIdHeader(): ?string
    {
        $header = config('multi-tenant.session.id_header', 'X-Session-Id');

        return $header !== '' ? (string) $header : null;
    }

    /**
     * Get the tenant column name on the session model.
     */
    protected function getSessionTenantColumn(): string
    {
        // Prefer explicit session.tenant_column, otherwise fall back to global tenant_column
        return (string) (config('multi-tenant.session.tenant_column')
            ?? config('multi-tenant.tenant_column', 'tenant_id'));
    }

    /**
     * Get the tenant relationship name on the session model.
     * Used to load tenant from session relationship instead of separate query.
     */
    protected function getSessionTenantRelation(): string
    {
        return (string) config('multi-tenant.session.tenant_relation', 'tenant');
    }

    /**
     * Get the request from the container.
     */
    protected function getRequestFromContainer(): ?Request
    {
        try {
            if (App::bound('request')) {
                /** @var Request $request */
                $request = App::make('request');

                return $request;
            }
        } catch (Throwable) {
            // Request not available
        }

        return null;
    }
}
