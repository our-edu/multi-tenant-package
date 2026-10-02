<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Ouredu\MultiTenant\Iam\ErrorResponse;
use Ouredu\MultiTenant\Iam\IamConfig;
use Ouredu\MultiTenant\Iam\PermissionAuthorizer;

/**
 * Usage: ->middleware('permission:classrooms.index') or
 * 'permission:classrooms.index|classrooms.show,api'; any one permission passes.
 */
class PermissionMiddleware
{
    public function __construct(private readonly PermissionAuthorizer $authorizer)
    {
    }

    public function handle(Request $request, Closure $next, string|array $permission, ?string $guard = null): mixed
    {
        // Guests get 403, not 401, to keep the status clients already handle
        if (auth($guard ?? IamConfig::get('guard'))->guest()) {
            throw ErrorResponse::unauthorizedAction();
        }

        $permissions = is_array($permission) ? $permission : explode('|', $permission);
        foreach ($permissions as $candidate) {
            [$resource, $action] = self::parse($candidate);
            if ($this->authorizer->allows($resource, $action)) {
                return $next($request);
            }
        }

        throw ErrorResponse::permissionDenied($permissions);
    }

    /**
     * @return array{string, string}
     */
    private static function parse(string $permission): array
    {
        $parts = explode('.', $permission, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidArgumentException("Permission \"$permission\" must look like resource.action");
        }

        return $parts;
    }
}
