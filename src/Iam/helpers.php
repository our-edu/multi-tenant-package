<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

use Ouredu\MultiTenant\Iam\PermissionAuthorizer;
use Ouredu\MultiTenant\Iam\TokenClaimsResolver;

if (! function_exists('token_claims')) {
    function token_claims(): TokenClaimsResolver
    {
        return app(TokenClaimsResolver::class);
    }
}

if (! function_exists('iam_can')) {
    /**
     * Whether IAM grants the current token this permission; 503 if IAM is down.
     */
    function iam_can(string $resource, string $action): bool
    {
        return app(PermissionAuthorizer::class)->allows($resource, $action);
    }
}
