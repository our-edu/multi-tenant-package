<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ouredu\MultiTenant\Iam\ErrorResponse;
use Ouredu\MultiTenant\Iam\Middleware\Concerns\GuardsIamRoutes;
use Ouredu\MultiTenant\Iam\TokenClaimsResolver;

/**
 * Usage: ->middleware('role:teacher|student') or 'role:teacher,api'.
 * Checks the IAM claims' role, and the active branch against the user's branches.
 */
class RoleMiddleware
{
    use GuardsIamRoutes;

    public function __construct(private readonly TokenClaimsResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next, string|array $role, ?string $guard = null): mixed
    {
        $this->denyGuests($guard);

        $claims = $this->resolver->requireClaims();

        if (! in_array($claims->role_name, self::alternatives($role), true)) {
            throw ErrorResponse::unauthorizedAction();
        }

        if ($claims->check_branch &&
            ! in_array('*', $claims->user_branches, true) &&
            ! in_array($claims->branch_uuid, $claims->user_branches, true)
        ) {
            throw ErrorResponse::unauthorizedAction();
        }

        return $next($request);
    }
}
