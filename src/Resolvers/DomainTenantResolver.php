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
 * Resolves the tenant of a public (no session, no X-Tenant-ID) request from the
 * school site that sent it: the Origin header, then the Referer header.
 * The request host itself is not used by default: every school calls the same
 * API host, so it can't tell them apart.
 *
 * Origin and Referer are set by the browser and can be forged by other clients,
 * so this resolver is OFF unless the route is listed in `multi-tenant.domain.routes`.
 * List only public pages that show tenant-specific, non-sensitive content.
 *
 * Resolution flow:
 * 1. If running in console (and not unit tests) → skip
 * 2. If the route isn't in `multi-tenant.domain.routes` → skip
 * 3. Take the host of the first configured source (origin, referer, host)
 * 4. Find the active tenant whose domain column matches, then one listing the
 *    host in its clients JSON (dashboard/website domains)
 * 5. Return the tenant ID (primary key) as integer
 */
class DomainTenantResolver implements TenantResolver
{
    /**
     * Resolve the current tenant ID from the calling site's domain.
     */
    public function resolveTenantId(): ?int
    {
        // Skip resolution in console (except when running tests)
        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return null;
        }

        $request = $this->getRequestFromContainer();

        if (! $request || ! $this->isRouteAllowed($request)) {
            return null;
        }

        $host = $this->getHostFromRequest($request);

        if ($host === null) {
            return null;
        }

        return $this->resolveTenantIdByHost($host);
    }

    /**
     * Host of the first configured source that has one, lowercased, without port.
     */
    protected function getHostFromRequest(Request $request): ?string
    {
        foreach ($this->getSources() as $source) {
            $host = match ($source) {
                'origin' => parse_url((string) $request->headers->get('Origin'), PHP_URL_HOST),
                'referer' => parse_url((string) $request->headers->get('Referer'), PHP_URL_HOST),
                'host' => $request->getHost(),
                default => null,
            };

            if (is_string($host) && $host !== '') {
                return rtrim(strtolower($host), '.');
            }
        }

        return null;
    }

    /**
     * Find the active tenant for a host: the domain column first, then the clients JSON.
     */
    protected function resolveTenantIdByHost(string $host): ?int
    {
        $model = $this->getTenantModelInstance();

        if (! $model) {
            return null;
        }

        try {
            $query = fn () => $this->activeTenants($model);

            $id = $query()->where($this->getDomainColumn(), $host)->value($model->getKeyName());

            if ($id !== null) {
                return (int) $id;
            }

            return $this->resolveTenantIdFromClients($query()->whereNotNull($this->getClientsColumn())->get(), $host, $model);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Match the host against the clients JSON column:
     * {"dashboard": {"domain": "...", "is_active": true}, "website": {...}, "mobile": {"key": "..."}}
     */
    protected function resolveTenantIdFromClients(iterable $tenants, string $host, Model $model): ?int
    {
        $clientsColumn = $this->getClientsColumn();

        foreach ($tenants as $tenant) {
            $clients = $tenant->{$clientsColumn};
            $clients = is_string($clients) ? json_decode($clients, true) : $clients;

            foreach ((array) $clients as $client) {
                if (
                    is_array($client)
                    && strtolower((string) ($client['domain'] ?? '')) === $host
                    && ($client['is_active'] ?? true)
                ) {
                    return (int) $tenant->{$model->getKeyName()};
                }
            }
        }

        return null;
    }

    /**
     * Active tenants, without global scopes (the tenant table isn't tenant-scoped,
     * and a scope here could call back into tenant resolution).
     */
    protected function activeTenants(Model $model): mixed
    {
        $query = $model->newQueryWithoutScopes();
        $activeColumn = $this->getActiveColumn();

        return $activeColumn ? $query->where($activeColumn, true) : $query;
    }

    /**
     * Whether the current route may resolve its tenant by domain.
     */
    protected function isRouteAllowed(Request $request): bool
    {
        $routes = $this->getAllowedRoutes();

        // If no routes configured, resolver is disabled
        if (empty($routes)) {
            return false;
        }

        $route = $request->route();
        $candidates = array_filter([
            ltrim($request->path(), '/'),
            is_object($route) ? $route->getName() : null,
            is_object($route) ? $route->uri() : null,
        ]);

        foreach ($routes as $pattern) {
            foreach ($candidates as $value) {
                if ($this->matchesPattern($value, ltrim($pattern, '/'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a value matches a pattern (asterisk matches any characters).
     */
    protected function matchesPattern(string $value, string $pattern): bool
    {
        if ($value === $pattern) {
            return true;
        }

        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

            return (bool) preg_match($regex, $value);
        }

        return false;
    }

    /**
     * Get the request from the container.
     */
    protected function getRequestFromContainer(): ?Request
    {
        try {
            $request = App::make('request');

            return $request instanceof Request ? $request : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function getTenantModelInstance(): ?Model
    {
        $class = config('multi-tenant.tenant_model');

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return new $class();
    }

    /**
     * Routes (names, URIs or paths; wildcards allowed) where domain resolution is allowed.
     */
    protected function getAllowedRoutes(): array
    {
        return (array) config('multi-tenant.domain.routes', []);
    }

    /**
     * Where to read the host from, in order.
     */
    protected function getSources(): array
    {
        return (array) config('multi-tenant.domain.sources', ['origin', 'referer']);
    }

    protected function getDomainColumn(): string
    {
        return (string) config('multi-tenant.domain.column', 'domain');
    }

    protected function getActiveColumn(): ?string
    {
        $column = config('multi-tenant.domain.active_column', 'is_active');

        return $column ? (string) $column : null;
    }

    protected function getClientsColumn(): string
    {
        return (string) config('multi-tenant.clients.column', 'clients');
    }
}
