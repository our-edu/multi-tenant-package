<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Http;
use Ouredu\MultiTenant\Iam\ClaimsFailure;
use Ouredu\MultiTenant\Iam\Facades\TokenClaims;
use Ouredu\MultiTenant\Tenancy\TenantContext;

class FakesTest extends IamTestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/teachers', fn () => token_claims()->requireClaims()->user_uuid)->middleware('role:teacher');
        $router->get('/classrooms', fn () => 'ok')->middleware('permission:classrooms.index');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    public function test_fake_claims_resolve_without_iam_or_a_token(): void
    {
        $claims = TokenClaims::fake(['role_name' => 'teacher', 'user_uuid' => 'teacher-1']);

        $this->assertSame('teacher', $claims->role_name);
        $this->getJson('/teachers')->assertOk()->assertSee('teacher-1');
        $this->getJson('/teachers')->assertOk();
        Http::assertNothingSent();
    }

    public function test_fake_claims_have_working_defaults(): void
    {
        $claims = TokenClaims::fake();

        $this->assertSame('student', $claims->role_name);
        $this->assertSame(['*'], $claims->user_branches);
        $this->assertFalse($claims->check_branch);
        $this->getJson('/teachers')->assertStatus(403);
    }

    public function test_fake_failure(): void
    {
        TokenClaims::fakeFailure(ClaimsFailure::Unavailable);
        $this->getJson('/teachers')->assertStatus(503);

        TokenClaims::fakeFailure(ClaimsFailure::Rejected);
        $this->getJson('/teachers')->assertStatus(401);

        TokenClaims::fakeFailure(ClaimsFailure::MissingToken);
        $this->assertNull(TokenClaims::optionalClaims());
        Http::assertNothingSent();
    }

    public function test_fake_permissions(): void
    {
        TokenClaims::fakePermissions(['classrooms.index']);
        $this->getJson('/classrooms')->assertOk();
        $this->assertFalse(iam_can('classrooms', 'destroy'));

        TokenClaims::fakePermissions([]);
        $this->getJson('/classrooms')->assertStatus(403);

        TokenClaims::fakePermissions(['*']);
        $this->getJson('/classrooms')->assertOk();
        Http::assertNothingSent();
    }

    public function test_fakes_keep_the_tenant_the_test_set(): void
    {
        app(TenantContext::class)->setTenantId(7);

        TokenClaims::fake();
        TokenClaims::fakeFailure(ClaimsFailure::Unavailable);
        TokenClaims::fakePermissions(['*']);

        $this->assertSame(7, app(TenantContext::class)->getTenantId());
    }
}
