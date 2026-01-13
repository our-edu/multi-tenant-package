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
 * DomainTenantResolver
 *
 * Resolves the current tenant from the request host/domain for public routes.
 *
 * Resolution flow:
 * 1. If running in console (and not unit tests) → skip
 * 2. Get Request instance from the container
 * 3. Extract host or subdomain from request
 * 4. Query the configured tenant model by the configured domain column
 */
class DomainTenantResolver implements TenantResolver
{
    /**
     * The current request instance (optional, mainly for testing).
     */
    protected ?Request $request;

    /**
     * Create a new resolver instance.
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Resolve the current tenant model.
     */
    public function resolveTenant(): ?Model
    {
        // Skip resolution in console (except when running tests)
        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return null;
        }

        $request = $this->request ?? $this->getRequestFromContainer();

        if (! $request) {
            return null;
        }

        $domain = $this->getDomainFromRequest($request);

        if (! $domain) {
            return null;
        }

        return $this->resolveTenantByDomain($domain);
    }

    /**
     * Get domain (or subdomain) from the request.
     */
    protected function getDomainFromRequest(Request $request): ?string
    {
        $host = $request->getHost();

        if (! $host) {
            return null;
        }

        if ($this->shouldUseSubdomain()) {
            return $this->extractSubdomain($host);
        }

        return $host;
    }

    /**
     * Extract subdomain from host based on configuration.
     */
    protected function extractSubdomain(string $host): ?string
    {
        $baseDomain = $this->getBaseDomain();

        if (! $baseDomain) {
            // No base domain configured, best-effort: first part when multi-part host
            $parts = explode('.', $host);

            return count($parts) > 2 ? $parts[0] : null;
        }

        // Remove base domain to get subdomain
        // e.g., "school1.ouredu.com" with base "ouredu.com" → "school1"
        if (str_ends_with($host, '.' . $baseDomain)) {
            $subdomain = substr($host, 0, -(strlen($baseDomain) + 1));

            return $subdomain !== '' ? $subdomain : null;
        }

        return null;
    }

    /**
     * Resolve tenant by domain value.
     */
    protected function resolveTenantByDomain(string $domain): ?Model
    {
        $tenantModel = $this->getTenantModel();

        if (! $tenantModel || ! class_exists($tenantModel)) {
            return null;
        }

        $domainColumn = $this->getDomainColumn();

        try {
            /** @var Model|null $tenant */
            $tenant = $tenantModel::where($domainColumn, $domain)->first();

            return $tenant instanceof Model ? $tenant : null;
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
                /** @var Request $request */
                $request = App::make('request');

                return $request;
            }
        } catch (Throwable) {
            // Request not available
        }

        return null;
    }

    /**
     * Get the tenant model class.
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

    /**
     * Determine if we should use subdomains for tenant resolution.
     */
    protected function shouldUseSubdomain(): bool
    {
        return (bool) config('multi-tenant.domain.use_subdomain', false);
    }

    /**
     * Get the base domain used to extract subdomains.
     */
    protected function getBaseDomain(): ?string
    {
        $base = config('multi-tenant.domain.base_domain');

        return $base !== '' ? $base : null;
    }
}
