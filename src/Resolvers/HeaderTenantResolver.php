<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Resolvers;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Throwable;

/**
 * HeaderTenantResolver
 *
 * Resolves the current tenant ID from a request header.
 * This resolver is useful for API routes where the tenant ID is passed
 * as a header parameter (e.g., X-Tenant-ID).
 *
 * Resolution flow:
 * 1. If running in console (and not unit tests) → skip
 * 2. Check if current route matches the configured routes
 * 3. Get tenant ID from the configured request header
 * 4. Return the tenant ID as integer
 */
class HeaderTenantResolver implements TenantResolver
{
    /**
     * Resolve the current tenant ID from the request header.
     *
     * @return int|null The resolved tenant ID or null if not found.
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

        // Check if current route is in the allowed routes list
        if (! $this->isRouteAllowed($request)) {
            return null;
        }

        return $this->getTenantIdFromHeader($request);
    }

    /**
     * Get the request from the service container.
     *
     * @return Request|null The current request or null if unavailable.
     */
    protected function getRequestFromContainer(): ?Request
    {
        try {
            return App::make('request');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check if the current route is in the allowed routes list.
     *
     * @param Request $request The current HTTP request.
     * @return bool True if the route is allowed, false otherwise.
     */
    protected function isRouteAllowed(Request $request): bool
    {
        $routes = $this->getAllowedRoutes();

        // If no routes configured, resolver is disabled
        if (empty($routes)) {
            return false;
        }

        $currentRoute = $request->route();

        if (! $currentRoute) {
            // Fall back to checking by path pattern
            return $this->isPathAllowed($request->path(), $routes);
        }

        $routeName = $currentRoute->getName();
        $routeUri = $currentRoute->uri();

        foreach ($routes as $route) {
            // Check by route name
            if ($routeName && $this->matchesPattern($routeName, $route)) {
                return true;
            }

            // Check by route URI/path
            if ($this->matchesPattern($routeUri, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request path matches any of the allowed routes.
     *
     * @param string $path The request path.
     * @param array $routes The list of allowed routes.
     * @return bool True if the path is allowed, false otherwise.
     */
    protected function isPathAllowed(string $path, array $routes): bool
    {
        foreach ($routes as $route) {
            if ($this->matchesPattern($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value matches a pattern (supports wildcards).
     *
     * @param string $value The value to check.
     * @param string $pattern The pattern to match against.
     * @return bool True if the value matches the pattern, false otherwise.
     */
    protected function matchesPattern(string $value, string $pattern): bool
    {
        // Exact match
        if ($value === $pattern) {
            return true;
        }

        // Wildcard pattern matching
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

            return (bool) preg_match($regex, $value);
        }

        return false;
    }

    /**
     * Get the tenant ID from the request header using JWT.
     *
     * @param Request $request The current HTTP request.
     * @return int|null The tenant ID or null if not found.
     * @throws Exception If the token is invalid or expired.
     */
    protected function getTenantIdFromHeader(Request $request): ?int
    {
        $headerName = $this->getHeaderName();
        $headerValue = $request->header($headerName);

        if ($headerValue === null || $headerValue === '') {
            return null;
        }

        try {
            if (substr_count($headerValue, '.') !== 2) {
                throw new Exception('Invalid token structure. Wrong number of segments.');
            }

            $secretKey = config('multi-tenant.jwt.secret', 'your-secret-key');
            $decoded = JWT::decode($headerValue, new Key($secretKey, 'HS256'));

            if (! isset($decoded->tenant_id, $decoded->exp)) {
                throw new Exception('Invalid token structure. Missing tenant_id or expiration.');
            }

            if (! is_numeric($decoded->tenant_id)) {
                return null;
            }

            if ($decoded->exp < time()) {
                throw new Exception('Expired token');
            }

            return (int) $decoded->tenant_id;
        } catch (Throwable $e) {
            throw new Exception($e->getMessage(), 401); // Return exception with 401 status code
        }
    }

    /**
     * Get the configured header name.
     *
     * @return string The header name to use for tenant resolution.
     */
    protected function getHeaderName(): string
    {
        return config('multi-tenant.header.name', 'X-Tenant-ID');
    }

    /**
     * Get the list of routes where header resolution is allowed.
     *
     * @return array The list of allowed routes.
     */
    protected function getAllowedRoutes(): array
    {
        return config('multi-tenant.header.routes', []);
    }
}
