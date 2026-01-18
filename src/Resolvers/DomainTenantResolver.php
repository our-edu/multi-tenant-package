<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Throwable;

/**
 * DomainTenantResolver
 *
 * Resolves the current tenant ID from the request domain.
 * Queries the tenant table by domain column and returns the tenant ID.
 *
 * Resolution flow:
 * 1. If running in console (and not unit tests) → skip
 * 2. Get the domain from the current request
 * 3. Query tenant table by domain column
 * 4. Return the tenant ID (primary key) as integer
 */
class DomainTenantResolver implements TenantResolver
{
    /**
     * Resolve the current tenant ID from the request domain.
     */
    public function resolveTenantId(): ?int
    {
        // Skip resolution in console (except when running tests)
        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return null;
        }

        $request = $this->getRequestFromContainer();

        if (! $request) {
            return null;
        }

        $domain = $request->getHost();

        if (! $domain) {
            return null;
        }

        return $this->resolveTenantIdByDomain($domain);
    }

    /**
     * Query tenant table by domain and return the tenant ID.
     */
    protected function resolveTenantIdByDomain(string $domain): ?int
    {
        $tenantModel = $this->getTenantModel();

        if (! $tenantModel || ! class_exists($tenantModel)) {
            return null;
        }

        $domainColumn = $this->getDomainColumn();

        try {
            $tenantId = $tenantModel::query()
                ->where($domainColumn, $domain)
                ->value('id');

            return $tenantId !== null ? (int) $tenantId : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get the request from the container.
     */
    protected function getRequestFromContainer(): ?Request
    {
        try {
            if (App::bound('request')) {
                $request = App::make('request');

                return $request instanceof Request ? $request : null;
            }
        } catch (Throwable) {
            // Request not available
        }

        return null;
    }

    /**
     * Get the Tenant model class.
     */
    protected function getTenantModel(): ?string
    {
        return config('multi-tenant.tenant_model');
    }

    /**
     * Get the domain column name on the tenant model.
     */
    protected function getDomainColumn(): string
    {
        return (string) config('multi-tenant.domain.column', 'domain');
    }
}
