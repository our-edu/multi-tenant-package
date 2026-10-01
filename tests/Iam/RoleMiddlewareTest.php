<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Http;

class RoleMiddlewareTest extends IamTestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/students', fn () => 'ok')->middleware('role:student');
        $router->get('/staff', fn () => 'ok')->middleware('role:teacher|admin');
    }

    private function actingAsUser(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    private function getWithToken(string $uri)
    {
        return $this->withHeader('Authorization', 'Bearer token')->getJson($uri);
    }

    public function test_guests_get_403(): void
    {
        Http::fake();

        $this->getWithToken('/students')
            ->assertStatus(403)
            ->assertJsonPath('errors.0.title', 'unauthorized_action');
        Http::assertNothingSent();
    }

    public function test_an_allowed_role_passes(): void
    {
        Http::fake(['*' => Http::response($this->validClaims())]);
        $this->actingAsUser();

        $this->getWithToken('/students')->assertOk();
    }

    public function test_any_of_several_roles_passes(): void
    {
        Http::fake(['*' => Http::response($this->validClaims(['role_name' => 'admin']))]);
        $this->actingAsUser();

        $this->getWithToken('/staff')->assertOk();
    }

    public function test_a_role_not_in_the_list_gets_403(): void
    {
        Http::fake(['*' => Http::response($this->validClaims(['role_name' => 'parent']))]);
        $this->actingAsUser();

        $this->getWithToken('/staff')
            ->assertStatus(403)
            ->assertJsonPath('errors.0.title', 'unauthorized_action');
    }

    public function test_the_active_branch_must_be_one_of_the_users_branches(): void
    {
        Http::fake(['*' => Http::response($this->validClaims([
            'branch' => 'branch-2',
            'user_branches' => ['branch-1'],
        ]))]);
        $this->actingAsUser();

        $this->getWithToken('/students')->assertStatus(403);
    }

    public function test_a_matching_branch_passes(): void
    {
        Http::fake(['*' => Http::response($this->validClaims([
            'branch' => 'branch-1',
            'user_branches' => ['branch-1', 'branch-3'],
        ]))]);
        $this->actingAsUser();

        $this->getWithToken('/students')->assertOk();
    }

    public function test_a_user_with_all_branches_passes(): void
    {
        Http::fake(['*' => Http::response($this->validClaims([
            'branch' => 'branch-9',
            'user_branches' => ['*'],
        ]))]);
        $this->actingAsUser();

        $this->getWithToken('/students')->assertOk();
    }

    public function test_iam_refusing_the_token_gets_401(): void
    {
        Http::fake(['*' => Http::response([], 401)]);
        $this->actingAsUser();

        $this->getWithToken('/students')
            ->assertStatus(401)
            ->assertJsonPath('errors.0.title', 'invalid_session');
    }

    public function test_iam_being_down_gets_503(): void
    {
        Http::fake(['*' => Http::response('boom', 502)]);
        $this->actingAsUser();

        $this->getWithToken('/students')
            ->assertStatus(503)
            ->assertJsonPath('errors.0.title', 'session_service_unavailable');
    }
}
