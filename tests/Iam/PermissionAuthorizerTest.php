<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Ouredu\MultiTenant\Iam\PermissionAuthorizer;

class PermissionAuthorizerTest extends IamTestCase
{
    private function authorizer(): PermissionAuthorizer
    {
        return $this->app->make(PermissionAuthorizer::class);
    }

    private function assertUnavailable(): void
    {
        try {
            $this->authorizer()->allows('classrooms', 'index');
            $this->fail('Expected allows() to stop the request');
        } catch (HttpResponseException $e) {
            $this->assertSame(503, $e->getResponse()->getStatusCode());
            $this->assertSame('session_service_unavailable', $e->getResponse()->getData(true)['errors'][0]['title']);
        }
    }

    public function test_it_allows_what_iam_authorizes(): void
    {
        Http::fake(['*' => Http::response(['authorized' => true])]);
        $this->withBearer('token');

        $this->assertTrue($this->authorizer()->allows('classrooms', 'index'));
        Http::assertSent(fn (ClientRequest $request) =>
            $request->method() === 'POST'
            && $request->url() === 'http://iam.test/api/v1/authorize'
            && $request->hasHeader('Authorization', 'Bearer token')
            && $request['resource'] === 'classrooms'
            && $request['action'] === 'index'
            && $request['token'] === 'token');
    }

    public function test_it_denies_what_iam_does_not_authorize(): void
    {
        Http::fake(['*' => Http::response(['authorized' => false])]);
        $this->withBearer('token');

        $this->assertFalse($this->authorizer()->allows('classrooms', 'index'));
    }

    public function test_it_denies_when_iam_refuses_the_token(): void
    {
        Http::fake(['*' => Http::response([], 401)]);
        $this->withBearer('token');

        $this->assertFalse($this->authorizer()->allows('classrooms', 'index'));
    }

    public function test_it_denies_without_calling_iam_when_there_is_no_bearer_token(): void
    {
        Http::fake();
        $this->withBearer(null);

        $this->assertFalse($this->authorizer()->allows('classrooms', 'index'));
        Http::assertNothingSent();
    }

    public function test_iam_errors_stop_the_request_with_503(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $this->withBearer('token');

        $this->assertUnavailable();
    }

    public function test_iam_being_unreachable_stops_the_request_with_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        $this->withBearer('token');

        $this->assertUnavailable();
    }

    public function test_each_permission_is_asked_once_per_request(): void
    {
        Http::fake(['*' => Http::response(['authorized' => false])]);
        $this->withBearer('token');

        foreach (range(1, 3) as $ignored) {
            $this->authorizer()->allows('classrooms', 'index');
            $this->authorizer()->allows('classrooms', 'show');
        }

        Http::assertSentCount(2);
    }

    public function test_the_helper_asks_the_authorizer(): void
    {
        Http::fake(['*' => Http::response(['authorized' => true])]);
        $this->withBearer('token');

        $this->assertTrue(iam_can('classrooms', 'index'));
    }
}
