<?php

declare(strict_types=1);

namespace Tests\Traits;

use Ouredu\MultiTenant\Exceptions\TenantNotFoundException;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Traits\SetsTenantFromPayload;
use stdClass;
use Tests\TestCase;

class SetsTenantFromPayloadTest extends TestCase
{
    private object $listenerWithTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an anonymous class that uses the trait
        $this->listenerWithTrait = new class () {
            use SetsTenantFromPayload;
        };
    }

    /** @test */
    public function it_sets_tenant_from_array_payload(): void
    {
        $payload = ['tenant_id' => 5, 'data' => 'test'];

        $this->listenerWithTrait->setTenantFromPayload($payload);

        $context = app(TenantContext::class);
        $this->assertEquals(5, $context->getTenantId());
    }

    /** @test */
    public function it_sets_tenant_from_object_payload(): void
    {
        $payload = (object) ['tenant_id' => 10, 'data' => 'test'];

        $this->listenerWithTrait->setTenantFromPayload($payload);

        $context = app(TenantContext::class);
        $this->assertEquals(10, $context->getTenantId());
    }

    /** @test */
    public function it_uses_custom_tenant_column_from_config(): void
    {
        config(['multi-tenant.tenant_column' => 'organization_id']);

        $payload = ['organization_id' => 15, 'data' => 'test'];

        $this->listenerWithTrait->setTenantFromPayload($payload);

        $context = app(TenantContext::class);
        $this->assertEquals(15, $context->getTenantId());
    }

    /** @test */
    public function it_throws_exception_when_tenant_not_in_payload_and_fallback_disabled(): void
    {
        config(['multi-tenant.listener.fallback_to_database' => false]);

        $payload = ['data' => 'test']; // No tenant_id

        $this->expectException(TenantNotFoundException::class);
        $this->expectExceptionMessage('Tenant ID not found in payload');

        $this->listenerWithTrait->setTenantFromPayload($payload);
    }

    /** @test */
    public function it_converts_string_tenant_id_to_int(): void
    {
        $payload = ['tenant_id' => '25', 'data' => 'test'];

        $this->listenerWithTrait->setTenantFromPayload($payload);

        $context = app(TenantContext::class);
        $this->assertSame(25, $context->getTenantId());
    }

    /** @test */
    public function it_handles_nested_object_payload(): void
    {
        $payload = new stdClass();
        $payload->tenant_id = 30;
        $payload->nested = (object) ['key' => 'value'];

        $this->listenerWithTrait->setTenantFromPayload($payload);

        $context = app(TenantContext::class);
        $this->assertEquals(30, $context->getTenantId());
    }

    /** @test */
    public function it_throws_exception_when_fallback_enabled_but_no_active_tenant(): void
    {
        config([
            'multi-tenant.listener.fallback_to_database' => true,
            'multi-tenant.tenant_model' => 'NonExistentModel',
        ]);

        $payload = ['data' => 'test']; // No tenant_id

        $this->expectException(TenantNotFoundException::class);

        $this->listenerWithTrait->setTenantFromPayload($payload);
    }
}
