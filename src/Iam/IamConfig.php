<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam;

use RuntimeException;

/**
 * IamConfig
 *
 * Reads the `multi-tenant.iam` config block. Every key has a default here, so
 * a service whose published config has no (or a partial) `iam` block works:
 * Laravel merges package config one level deep only.
 */
final class IamConfig
{
    private const DEFAULTS = [
        'timeout' => 10,
        'guard' => null,
        'register_middleware_aliases' => false,
        'show_permissions_in_error' => null,
    ];

    public static function get(string $key): mixed
    {
        $default = data_get(self::DEFAULTS, $key);

        return config("multi-tenant.iam.$key") ?? $default;
    }

    /**
     * An IAM endpoint, on the service's own config('app.iam_service_url')
     * (IAM_SERVICE_URL); the package keeps no IAM URL of its own.
     *
     * @throws RuntimeException when the service has no app.iam_service_url
     */
    public static function url(string $path): string
    {
        $base = (string) config('app.iam_service_url');

        if ($base === '') {
            throw new RuntimeException(
                'config(\'app.iam_service_url\') is not set; add \'iam_service_url\' => env(\'IAM_SERVICE_URL\') to config/app.php.'
            );
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    public static function timeout(): int
    {
        return (int) self::get('timeout');
    }
}
