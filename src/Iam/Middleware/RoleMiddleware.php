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
use Ouredu\MultiTenant\Iam\IamConfig;
use Ouredu\MultiTenant\Iam\TokenClaimsResolver;

/**
 * Usage: ->middleware('role:teacher|student') or 'role:teacher,api'.
 * Checks the IAM claims' role, and the active branch against the user's branches.
 */
class RoleMiddleware
{
    public function __construct(private readonly TokenClaimsResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next, string|array $role, ?string $guard = null): mixed
    {
        // Guests get 403, not 401, to keep the status clients already handle
        if (auth($guard ?? IamConfig::get('guard'))->guest()) {
            throw ErrorResponse::unauthorizedAction();
        }

        $claims = $this->resolver->requireClaims();

        $allowedRoles = is_array($role) ? $role : explode('|', $role);
        if (! in_array($claims->role_name, $allowedRoles, true)) {
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
