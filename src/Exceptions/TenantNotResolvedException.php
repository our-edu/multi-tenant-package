<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Exceptions;

use RuntimeException;

/**
 * TenantNotResolvedException
 *
 * Thrown when no tenant resolver is able to resolve a tenant ID
 * for a route that requires tenant context.
 */
class TenantNotResolvedException extends RuntimeException
{
    /**
     * Default translation key for the exception message.
     */
    protected const DEFAULT_TRANSLATION_KEY = 'multi-tenant::exceptions.tenant_not_resolved';

    /**
     * Default fallback message when translation is not available.
     */
    protected const DEFAULT_MESSAGE = 'Unable to resolve tenant. No resolver returned a valid tenant ID.';

    public function __construct(?string $message = null)
    {
        $message = $message ?? $this->getTranslatedMessage();

        parent::__construct($message);
    }

    /**
     * Get the translated message or fall back to default.
     */
    protected function getTranslatedMessage(): string
    {
        if (! function_exists('__')) {
            return self::DEFAULT_MESSAGE;
        }

        $translated = __(self::DEFAULT_TRANSLATION_KEY);

        // If translation key is returned as-is, use default message
        if ($translated === self::DEFAULT_TRANSLATION_KEY) {
            return self::DEFAULT_MESSAGE;
        }

        return $translated;
    }
}

