<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Ouredu\MultiTenant\Resolvers\HeaderTenantResolver;
use Tests\TestCase;

class HeaderTenantResolverTest extends TestCase
{
    public function testResolveTenantIdReturnsNullInConsole(): void
    {
        // PHPUnit runs in console, so this should return null
        $resolver = new HeaderTenantResolver();

        $tenantId = $resolver->resolveTenantId();

        $this->assertNull($tenantId);
    }

    public function testGetHeaderNameDefaultsToXTenantId(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedGetHeaderName(): string
            {
                return $this->getHeaderName();
            }
        };

        $this->assertEquals('X-Tenant-ID', $resolver->exposedGetHeaderName());
    }

    public function testGetHeaderNameUsesConfig(): void
    {
        config(['multi-tenant.header.name' => 'X-Custom-Tenant']);

        $resolver = new class () extends HeaderTenantResolver {
            public function exposedGetHeaderName(): string
            {
                return $this->getHeaderName();
            }
        };

        $this->assertEquals('X-Custom-Tenant', $resolver->exposedGetHeaderName());
    }

    public function testGetAllowedRoutesDefaultsToEmptyArray(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedGetAllowedRoutes(): array
            {
                return $this->getAllowedRoutes();
            }
        };

        $this->assertEquals([], $resolver->exposedGetAllowedRoutes());
    }

    public function testGetAllowedRoutesUsesConfig(): void
    {
        config(['multi-tenant.header.routes' => ['api/external/*', 'webhook.process']]);

        $resolver = new class () extends HeaderTenantResolver {
            public function exposedGetAllowedRoutes(): array
            {
                return $this->getAllowedRoutes();
            }
        };

        $this->assertEquals(['api/external/*', 'webhook.process'], $resolver->exposedGetAllowedRoutes());
    }

    public function testMatchesPatternWithExactMatch(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedMatchesPattern(string $value, string $pattern): bool
            {
                return $this->matchesPattern($value, $pattern);
            }
        };

        $this->assertTrue($resolver->exposedMatchesPattern('api/external', 'api/external'));
        $this->assertFalse($resolver->exposedMatchesPattern('api/internal', 'api/external'));
    }

    public function testMatchesPatternWithWildcard(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedMatchesPattern(string $value, string $pattern): bool
            {
                return $this->matchesPattern($value, $pattern);
            }
        };

        $this->assertTrue($resolver->exposedMatchesPattern('api/external/users', 'api/external/*'));
        $this->assertTrue($resolver->exposedMatchesPattern('api/external/orders/123', 'api/external/*'));
        $this->assertFalse($resolver->exposedMatchesPattern('api/internal/users', 'api/external/*'));
    }

    public function testGetTenantIdFromHeaderReturnsInteger(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedGetTenantIdFromHeader(Request $request): ?int
            {
                return $this->getTenantIdFromHeader($request);
            }
        };

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Tenant-ID', '42');

        $this->assertSame(42, $resolver->exposedGetTenantIdFromHeader($request));
    }

    public function testGetTenantIdFromHeaderReturnsNullForNonNumeric(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedGetTenantIdFromHeader(Request $request): ?int
            {
                return $this->getTenantIdFromHeader($request);
            }
        };

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Tenant-ID', 'not-a-number');

        $this->assertNull($resolver->exposedGetTenantIdFromHeader($request));
    }

    public function testGetTenantIdFromHeaderReturnsNullWhenMissing(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedGetTenantIdFromHeader(Request $request): ?int
            {
                return $this->getTenantIdFromHeader($request);
            }
        };

        $request = Request::create('/test', 'GET');

        $this->assertNull($resolver->exposedGetTenantIdFromHeader($request));
    }

    public function testIsRouteAllowedReturnsFalseWhenNoRoutesConfigured(): void
    {
        config(['multi-tenant.header.routes' => []]);

        $resolver = new class () extends HeaderTenantResolver {
            public function exposedIsRouteAllowed(Request $request): bool
            {
                return $this->isRouteAllowed($request);
            }
        };

        $request = Request::create('/api/external/test', 'GET');

        $this->assertFalse($resolver->exposedIsRouteAllowed($request));
    }

    public function testIsPathAllowedMatchesWildcardPatterns(): void
    {
        $resolver = new class () extends HeaderTenantResolver {
            public function exposedIsPathAllowed(string $path, array $routes): bool
            {
                return $this->isPathAllowed($path, $routes);
            }
        };

        $routes = ['api/external/*', 'webhook'];

        $this->assertTrue($resolver->exposedIsPathAllowed('api/external/users', $routes));
        $this->assertTrue($resolver->exposedIsPathAllowed('webhook', $routes));
        $this->assertFalse($resolver->exposedIsPathAllowed('api/internal/users', $routes));
    }
}

