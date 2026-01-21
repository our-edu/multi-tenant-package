<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use Ouredu\MultiTenant\Exceptions\TenantNotFoundException;
use RuntimeException;
use Tests\TestCase;

class TenantNotFoundExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_missing_in_payload_exception(): void
    {
        $exception = TenantNotFoundException::missingInPayload();

        $this->assertInstanceOf(TenantNotFoundException::class, $exception);
        $this->assertStringContainsString('not found in payload', $exception->getMessage());
    }

    /** @test */
    public function it_creates_no_active_tenant_exception(): void
    {
        $exception = TenantNotFoundException::noActiveTenant();

        $this->assertInstanceOf(TenantNotFoundException::class, $exception);
        $this->assertStringContainsString('No active tenant', $exception->getMessage());
    }

    /** @test */
    public function it_creates_not_resolved_exception(): void
    {
        $exception = TenantNotFoundException::notResolved();

        $this->assertInstanceOf(TenantNotFoundException::class, $exception);
        $this->assertStringContainsString('could not be resolved', $exception->getMessage());
    }

    /** @test */
    public function it_extends_runtime_exception(): void
    {
        $exception = new TenantNotFoundException('Custom message');

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertEquals('Custom message', $exception->getMessage());
    }
}
