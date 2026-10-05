<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Tenancy;

use Ouredu\MultiTenant\Tenancy\CurrentSession;
use Tests\Support\FakeSession;
use Tests\TestCase;

class CurrentSessionTest extends TestCase
{
    protected function tearDown(): void
    {
        FakeSession::reset();
        parent::tearDown();
    }

    public function test_it_returns_the_helper_session(): void
    {
        config(['multi-tenant.session.helper' => 'fake_timezone_session']);
        FakeSession::$session = (object) ['tenant_id' => 3];

        $this->assertSame(FakeSession::$session, CurrentSession::get());
    }

    public function test_it_is_null_without_the_helper(): void
    {
        config(['multi-tenant.session.helper' => 'no_such_session_helper']);

        $this->assertNull(CurrentSession::get());
    }

    public function test_it_is_null_when_the_helper_returns_no_object(): void
    {
        config(['multi-tenant.session.helper' => 'fake_timezone_session']);
        FakeSession::$session = null;

        $this->assertNull(CurrentSession::get());
    }

    public function test_it_swallows_a_helper_that_throws(): void
    {
        config(['multi-tenant.session.helper' => 'fake_rejecting_session']);

        $this->assertNull(CurrentSession::get());
    }

    public function test_attribute_names_default(): void
    {
        $this->assertSame('getSession', CurrentSession::helperName());
        $this->assertSame('tenant_id', CurrentSession::tenantAttribute());
        $this->assertSame('timezone', CurrentSession::timezoneAttribute());
        $this->assertSame('branch_uuid', CurrentSession::branchAttribute());
    }

    public function test_attribute_names_follow_the_config(): void
    {
        config([
            'multi-tenant.session.tenant_column' => 'school_id',
            'multi-tenant.timezone.session_attribute' => 'tz',
            'multi-tenant.timezone.session_branch_attribute' => 'active_branch',
        ]);

        $this->assertSame('school_id', CurrentSession::tenantAttribute());
        $this->assertSame('tz', CurrentSession::timezoneAttribute());
        $this->assertSame('active_branch', CurrentSession::branchAttribute());
    }

    public function test_the_tenant_attribute_falls_back_to_the_global_tenant_column(): void
    {
        config([
            'multi-tenant.session.tenant_column' => null,
            'multi-tenant.tenant_column' => 'org_id',
        ]);

        $this->assertSame('org_id', CurrentSession::tenantAttribute());
    }
}
