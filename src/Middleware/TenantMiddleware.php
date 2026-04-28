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
 *
 * Supports path patterns with wildcards (asterisk matches any single segment).
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Skip tenant resolution for OPTIONS preflight requests (CORS)
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

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

        $currentPath = ltrim($request->path(), '/');

        foreach ($excludedRoutes as $pattern) {
            if ($this->pathMatchesPattern($currentPath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a pattern with wildcard support.
     * Wildcards match any single path segment.
     */
    protected function pathMatchesPattern(string $path, string $pattern): bool
    {
        $pattern = ltrim($pattern, '/');

        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Convert wildcard pattern to regex
        // Escape special regex characters except asterisk
        $regex = preg_quote($pattern, '#');

        // Replace escaped asterisk with regex pattern to match any single segment
        $regex = str_replace('\*', '[^/]+', $regex);

        // Add anchors for full path match
        $regex = '#^' . $regex . '$#';

        return (bool) preg_match($regex, $path);
    }

    /**
     * Get the list of excluded routes from config.
     */
    protected function getExcludedRoutes(): array
    {
        $excludedRoutes = config('multi-tenant.excluded_routes', []);

        $prefix = config('multi-tenant.production_app_prefix');

        if (!empty($prefix) && !empty($excludedRoutes)) {
            $prefix = trim($prefix, '/');
            $excludedRoutes = array_map(function ($route) use ($prefix) {
                return $prefix . '/' . ltrim($route, '/');
            }, $excludedRoutes);
        }

        return $excludedRoutes;
    }
}
