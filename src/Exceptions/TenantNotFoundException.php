<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Exceptions;

use RuntimeException;

/**
 * TenantNotFoundException
 *
 * Thrown when tenant cannot be resolved and is required for the operation.
 *
 * Services can override messages by passing custom translated strings:
 * @example TenantNotFoundException::missingInPayload(__('custom.message'))
 */
class TenantNotFoundException extends RuntimeException
{
    public const DEFAULT_MISSING_IN_PAYLOAD = 'Tenant ID not found in payload and fallback is disabled or no active tenant exists.';

    public const DEFAULT_NO_ACTIVE_TENANT = 'No active tenant found in the database.';

    public const DEFAULT_NOT_RESOLVED = 'Tenant ID could not be resolved. No resolver returned a valid tenant.';

    /**
     * Create a new exception for missing tenant in payload.
     */
    public static function missingInPayload(?string $message = null): self
    {
        return new self($message ?? self::DEFAULT_MISSING_IN_PAYLOAD);
    }

    /**
     * Create a new exception for no active tenant in database.
     */
    public static function noActiveTenant(?string $message = null): self
    {
        return new self($message ?? self::DEFAULT_NO_ACTIVE_TENANT);
    }

    /**
     * Create a new exception when resolvers fail to resolve tenant.
     */
    public static function notResolved(?string $message = null): self
    {
        return new self($message ?? self::DEFAULT_NOT_RESOLVED);
    }
}
