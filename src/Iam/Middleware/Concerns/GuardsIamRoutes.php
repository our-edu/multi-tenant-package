<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam\Middleware\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Ouredu\MultiTenant\Iam\ErrorResponse;
use Ouredu\MultiTenant\Iam\IamConfig;

/**
 * Shared by the role and permission middleware.
 */
trait GuardsIamRoutes
{
    /**
     * Guests get 403, not 401, to keep the status clients already handle.
     *
     * @throws HttpResponseException
     */
    protected function denyGuests(?string $guard): void
    {
        if (auth($guard ?? IamConfig::get('guard'))->guest()) {
            throw ErrorResponse::unauthorizedAction();
        }
    }

    /**
     * The alternatives of a middleware argument: 'a|b' or ['a', 'b'] gives ['a', 'b'].
     *
     * @param string|string[] $value
     * @return string[]
     */
    protected static function alternatives(string|array $value): array
    {
        return is_array($value) ? $value : explode('|', $value);
    }
}
