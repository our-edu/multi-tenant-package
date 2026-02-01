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
 */
class ChainTenantResolver implements TenantResolver
{
    /**
     * List of tenant resolvers.
     *
     * @var TenantResolver[]
     */
    private array $resolvers;

    /**
     * Constructor.
     *
     * @param TenantResolver[] $resolvers List of resolvers to chain.
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers ?: $this->getDefaultResolvers();
    }

    /**
     * Resolve the current tenant ID by trying each resolver in order.
     *
     * @return int|null The resolved tenant ID, or null if none found.
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
            new UserSessionTenantResolver(), // Check UserSessionTenantResolver first
            new HeaderTenantResolver(),     // Then check HeaderTenantResolver
        ];
    }
}
