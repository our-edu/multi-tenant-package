<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ouredu\MultiTenant\Exceptions\TenantNotResolvedException;
use Ouredu\MultiTenant\Tenancy\TenantContext;

/**
 * TenantMiddleware
 *
 * Middleware that ensures the TenantContext is resolved early
 * in the request lifecycle. Supports excluded routes that bypass
 * tenant resolution entirely.
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Skip tenant resolution for excluded routes
        if ($this->isRouteExcluded($request)) {
            return $next($request);
        }

        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        // Trigger lazy resolution and ensure tenant is resolved
        $tenantId = $context->getTenantId();

        if ($tenantId === null) {
            throw new TenantNotResolvedException();
        }

        return $next($request);
    }

    /**
     * Check if the current route is in the excluded routes list.
     */
    protected function isRouteExcluded(Request $request): bool
    {
        $excludedRoutes = $this->getExcludedRoutes();

        if (empty($excludedRoutes)) {
            return false;
        }

        $currentRoute = $request->route();

        if (! $currentRoute) {
            return $this->isPathExcluded($request->path(), $excludedRoutes);
        }

        $routeName = $currentRoute->getName();
        $routeUri = $currentRoute->uri();

        foreach ($excludedRoutes as $route) {
            if ($routeName && $this->matchesPattern($routeName, $route)) {
                return true;
            }

            if ($this->matchesPattern($routeUri, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request path matches any of the excluded routes.
     */
    protected function isPathExcluded(string $path, array $excludedRoutes): bool
    {
        foreach ($excludedRoutes as $route) {
            if ($this->matchesPattern($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value matches a pattern (supports wildcards).
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
     * Get the list of excluded routes from config.
     */
    protected function getExcludedRoutes(): array
    {
        return config('multi-tenant.excluded_routes', []);
    }
}
