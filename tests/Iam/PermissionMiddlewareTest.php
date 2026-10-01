<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class PermissionMiddlewareTest extends IamTestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('/classrooms', fn () => 'ok')->middleware('permission:classrooms.index');
        $router->get('/either', fn () => 'ok')->middleware('permission:classrooms.index|classrooms.show');
        $router->get('/broken', fn () => 'ok')->middleware('permission:classrooms');
    }

    private function getWithToken(string $uri)
    {
        return $this->withHeader('Authorization', 'Bearer token')->getJson($uri);
    }

    private function actingAsUser(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    private function authorizeOnly(string $permission): void
    {
        Http::fake(fn (ClientRequest $request) => Http::response([
            'authorized' => "{$request['resource']}.{$request['action']}" === $permission,
        ]));
    }

    public function test_guests_get_403(): void
    {
        Http::fake();

        $this->getWithToken('/classrooms')
            ->assertStatus(403)
            ->assertJsonPath('errors.0.title', 'unauthorized_action');
        Http::assertNothingSent();
    }

    public function test_a_granted_permission_passes(): void
    {
        $this->authorizeOnly('classrooms.index');
        $this->actingAsUser();

        $this->getWithToken('/classrooms')->assertOk();
    }

    public function test_a_denied_permission_gets_403(): void
    {
        $this->authorizeOnly('classrooms.store');
        $this->actingAsUser();

        $this->getWithToken('/classrooms')
            ->assertStatus(403)
            ->assertJsonPath('errors.0.title', 'unauthorized_action')
            ->assertJsonPath('errors.0.detail', 'User does not have the right permissions.');
    }

    public function test_any_one_of_several_permissions_passes(): void
    {
        $this->authorizeOnly('classrooms.show');
        $this->actingAsUser();

        $this->getWithToken('/either')->assertOk();
    }

    public function test_the_detail_can_name_the_needed_permissions(): void
    {
        config(['permission.display_permission_in_exception' => true]);
        $this->authorizeOnly('nothing.granted');
        $this->actingAsUser();

        $this->getWithToken('/either')
            ->assertStatus(403)
            ->assertJsonPath(
                'errors.0.detail',
                'User does not have the right permissions. Necessary permissions are classrooms.index, classrooms.show'
            );
    }

    public function test_iam_being_down_gets_503(): void
    {
        Http::fake(['*' => Http::response('boom', 503)]);
        $this->actingAsUser();

        $this->getWithToken('/classrooms')
            ->assertStatus(503)
            ->assertJsonPath('errors.0.title', 'session_service_unavailable');
    }

    public function test_a_permission_without_an_action_is_a_developer_error(): void
    {
        Http::fake();
        $this->actingAsUser();

        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);

        $this->getWithToken('/broken');
    }
}
