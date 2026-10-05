<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam\Testing;

use Ouredu\MultiTenant\Iam\PermissionAuthorizer;

/**
 * Allows only the given "resource.action" permissions, or everything with '*'.
 */
class FakePermissionAuthorizer extends PermissionAuthorizer
{
    /**
     * @param string[] $allowed
     */
    public function __construct(private readonly array $allowed)
    {
    }

    protected function ask(string $resource, string $action): bool
    {
        return in_array('*', $this->allowed, true)
            || in_array("$resource.$action", $this->allowed, true);
    }
}
