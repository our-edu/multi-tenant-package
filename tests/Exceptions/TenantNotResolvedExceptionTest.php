<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Exceptions;

use Ouredu\MultiTenant\Exceptions\TenantNotResolvedException;
use RuntimeException;
use Tests\TestCase;

class TenantNotResolvedExceptionTest extends TestCase
{
    public function testExceptionHasDefaultMessage(): void
    {
        $exception = new TenantNotResolvedException();

        $this->assertEquals(
            __('multi-tenant::exceptions.tenant_not_resolved'),
            $exception->getMessage()
        );
    }

    public function testExceptionAcceptsCustomMessage(): void
    {
        $exception = new TenantNotResolvedException('Custom error message');

        $this->assertEquals('Custom error message', $exception->getMessage());
    }

    public function testExceptionExtendsRuntimeException(): void
    {
        $exception = new TenantNotResolvedException();

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    public function testExceptionUsesTranslatedMessage(): void
    {
        // Set a custom translation before creating the exception
        $this->app['translator']->addLines([
            'exceptions.tenant_not_resolved' => 'Custom translated message',
        ], 'en', 'multi-tenant');

        $exception = new TenantNotResolvedException();

        $this->assertEquals('Custom translated message', $exception->getMessage());
    }

    public function testExceptionCustomMessageOverridesTranslation(): void
    {
        // Set a translation
        $this->app['translator']->addLines([
            'exceptions.tenant_not_resolved' => 'Translated message',
        ], 'en', 'multi-tenant');

        // But pass a custom message - it should override the translation
        $exception = new TenantNotResolvedException('My custom message');

        $this->assertEquals('My custom message', $exception->getMessage());
    }
}
