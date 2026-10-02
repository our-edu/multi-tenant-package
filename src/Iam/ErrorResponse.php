<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Builds the JSON error body every service already returns.
 */
final class ErrorResponse
{
    public static function invalidSession(): HttpResponseException
    {
        return self::make(401, 'invalid_session', self::message('invalid_session'));
    }

    public static function serviceUnavailable(): HttpResponseException
    {
        return self::make(503, 'session_service_unavailable', self::message('session_service_unavailable'));
    }

    public static function unauthorizedAction(): HttpResponseException
    {
        return self::make(403, 'unauthorized_action', self::message('unauthorized_action'));
    }

    /**
     * @param string[] $permissions any one of which would have been enough
     */
    public static function permissionDenied(array $permissions): HttpResponseException
    {
        $show = IamConfig::get('show_permissions_in_error')
            ?? config('permission.display_permission_in_exception', false);

        $detail = $show
            ? self::message('permission_denied_detailed', ['permissions' => implode(', ', $permissions)])
            : self::message('permission_denied');

        return self::make(403, 'unauthorized_action', $detail);
    }

    private static function message(string $key, array $replace = []): string
    {
        // Services reword these by overriding lang/vendor/multi-tenant/{locale}/iam.php
        return (string) trans("multi-tenant::iam.$key", $replace);
    }

    private static function make(int $status, string $title, string $detail): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'errors' => [
                [
                    'status' => $status,
                    'title' => $title,
                    'detail' => $detail,
                ],
            ],
        ], $status));
    }
}
