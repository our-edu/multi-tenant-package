<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Ouredu\MultiTenant\Contracts\TenantResolver;

/**
 * ChainTenantResolver
 *
 * Chains multiple tenant resolvers together and tries each in order until one
 * returns a tenant.
 *
 * Default chain:
 * 1. UserSessionTenantResolver - For authenticated routes (shared session table)
 * 2. DomainTenantResolver      - For public routes (domain on tenant model)
 */
class ChainTenantResolver implements TenantResolver
{
    /**
     * The resolvers to chain.
     *
     * @var TenantResolver[]
     */
    protected array $resolvers;

    /**
     * Create a new chain resolver instance.
     *
     * @param TenantResolver[] $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        $this->resolvers = $resolvers ?: $this->getDefaultResolvers();
    }

    /**
     * Resolve the current tenant model, trying each resolver in order.
     */
    public function resolveTenant(): ?Model
    {
        foreach ($this->resolvers as $resolver) {
            $tenant = $resolver->resolveTenant();

            if ($tenant !== null) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Get the default resolver chain.
     *
     * @return TenantResolver[]
     */
    protected function getDefaultResolvers(): array
    {
        return [
            new UserSessionTenantResolver(),
            new DomainTenantResolver(),
        ];
    }

    /**
     * Add a resolver to the end of the chain.
     *
     * @return $this
     */
    public function addResolver(TenantResolver $resolver): static
    {
        $this->resolvers[] = $resolver;

        return $this;
    }

    /**
     * Prepend a resolver to the beginning of the chain.
     *
     * @return $this
     */
    public function prependResolver(TenantResolver $resolver): static
    {
        array_unshift($this->resolvers, $resolver);

        return $this;
    }
}


