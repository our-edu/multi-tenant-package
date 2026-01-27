<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Resolvers;

use Ouredu\MultiTenant\Contracts\TenantResolver;

/**
 * ChainTenantResolver
 *
 * Chains multiple tenant resolvers together, trying each one in order
 * until a tenant ID is resolved.
 *
 * Default order:
 * 1. HeaderTenantResolver - Uses request header for specific routes
 * 2. UserSessionTenantResolver - Uses getSession() helper
 * 3. DomainTenantResolver - Uses request domain/subdomain
 */
class ChainTenantResolver implements TenantResolver
{
    /**
     * @var TenantResolver[]
     */
    private array $resolvers;

    /**
     * @param TenantResolver[] $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers ?: $this->getDefaultResolvers();
    }

    /**
     * Resolve the current tenant ID by trying each resolver in order.
     */
    public function resolveTenantId(): ?int
    {
        foreach ($this->resolvers as $resolver) {
            $tenantId = $resolver->resolveTenantId();

            if ($tenantId !== null) {
                return $tenantId;
            }
        }

        return null;
    }

    /**
     * Get the default resolvers.
     *
     * @return TenantResolver[]
     */
    protected function getDefaultResolvers(): array
    {
        return [
            new HeaderTenantResolver(),
            new UserSessionTenantResolver(),
            new DomainTenantResolver(),
        ];
    }
}
