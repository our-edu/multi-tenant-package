<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Ouredu\MultiTenant\Iam\Concerns\HasTokenClaims;
use Ouredu\MultiTenant\Iam\Facades\TokenClaims;
use Ouredu\MultiTenant\Iam\TokenClaims as Claims;

class SessionBindingTest extends IamTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TokenClaims::bindSession(FakeUserSession::class);
    }

    public function test_the_session_is_built_from_the_claims(): void
    {
        Http::fake(['*' => Http::response($this->validClaims([
            'branch' => 'branch-1',
            'user_branches' => ['branch-1'],
            'academic_year_uuid' => 'year-1',
            'branch_educational_systems' => ['es-1'],
        ]))]);
        $this->withBearer('token');

        $session = app(FakeUserSession::class);

        $this->assertInstanceOf(FakeUserSession::class, $session);
        $this->assertSame('user-1', $session->user_uuid);
        $this->assertSame('user-1', $session->user_id);
        $this->assertSame('role-1', $session->role_uuid);
        $this->assertSame('role-1', $session->role_id);
        $this->assertSame('student', $session->role_name);
        $this->assertSame('branch-1', $session->branch_uuid);
        $this->assertSame(['branch-1'], $session->user_branches);
        $this->assertTrue($session->check_branch);
        $this->assertSame('year-1', $session->academic_year_uuid);
        $this->assertTrue($session->is_valid);
        $this->assertSame(1, $session->tenant_id);
        $this->assertSame(['es-1'], $session->branch_educational_systems);
    }

    public function test_services_can_map_extra_attributes(): void
    {
        Http::fake(['*' => Http::response($this->validClaims(['student_uuid' => 'student-1']))]);
        $this->withBearer('token');

        $this->assertSame('student-1', app(FakeUserSession::class)->student_uuid);
    }

    public function test_the_session_is_null_without_claims(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $this->withBearer('token');

        $this->assertNull(app(FakeUserSession::class));
    }

    public function test_each_request_gets_its_own_session(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->validClaims(['role_name' => 'student']))
            ->push($this->validClaims(['role_name' => 'teacher']))]);

        $this->withBearer('first');
        $this->assertSame('student', app(FakeUserSession::class)->role_name);

        $this->withBearer('second');
        $this->assertSame('teacher', app(FakeUserSession::class)->role_name);
    }

    public function test_optional_claims_is_null_only_without_a_token(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->withBearer(null);
        $this->assertNull(TokenClaims::optionalClaims());

        $this->withBearer('token');

        try {
            TokenClaims::optionalClaims();
            $this->fail('Expected optionalClaims() to stop the request');
        } catch (HttpResponseException $e) {
            $this->assertSame(503, $e->getResponse()->getStatusCode());
        }
    }

    public function test_the_facade_and_helper_reach_the_resolver(): void
    {
        Http::fake(['*' => Http::response($this->validClaims())]);
        $this->withBearer('token');

        $this->assertSame('student', TokenClaims::requireClaims()->role_name);
        $this->assertSame('student', token_claims()->claims()->role_name);
        Http::assertSentCount(1);
    }
}

class FakeUserSession extends Model
{
    use HasTokenClaims;

    protected function fillExtraFromTokenClaims(Claims $claims): void
    {
        $this->student_uuid = $claims->raw('student_uuid');
    }
}
