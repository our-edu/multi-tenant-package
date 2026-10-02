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
use Ouredu\MultiTenant\Iam\ClaimsFailure;
use Ouredu\MultiTenant\Iam\TokenClaims;
use Ouredu\MultiTenant\Iam\TokenClaimsResolver;
use PHPUnit\Framework\Attributes\DataProvider;

class TokenClaimsResolverTest extends IamTestCase
{
    private function resolver(): TokenClaimsResolver
    {
        return $this->app->make(TokenClaimsResolver::class);
    }

    private function assertRejects(int $status, string $title): void
    {
        try {
            $this->resolver()->requireClaims();
            $this->fail('Expected requireClaims() to reject the request');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame($title, $response->getData(true)['errors'][0]['title']);
        }
    }

    public function test_it_resolves_claims_from_iam(): void
    {
        Http::fake(['*' => Http::response($this->validClaims())]);
        $this->withBearer('token');

        $claims = $this->resolver()->requireClaims();

        $this->assertInstanceOf(TokenClaims::class, $claims);
        $this->assertSame('student', $claims->role_name);
        $this->assertNull($this->resolver()->failure());
        Http::assertSent(fn (ClientRequest $request) =>
            $request->url() === 'http://iam.test/api/v1/token/claims'
            && $request->hasHeader('Authorization', 'Bearer token'));
    }

    public function test_it_calls_the_services_iam_url(): void
    {
        config(['app.iam_service_url' => 'http://saas-iam-service:7777/iam/api/v1/']);
        Http::fake(['*' => Http::response($this->validClaims())]);
        $this->withBearer('token');

        $this->resolver()->claims();

        Http::assertSent(fn (ClientRequest $request) =>
            $request->url() === 'http://saas-iam-service:7777/iam/api/v1/token/claims');
    }

    public function test_it_returns_null_without_calling_iam_when_there_is_no_bearer_token(): void
    {
        Http::fake();
        $this->withBearer(null);

        $this->assertNull($this->resolver()->claims());
        $this->assertSame(ClaimsFailure::MissingToken, $this->resolver()->failure());
        Http::assertNothingSent();
        $this->assertRejects(401, 'invalid_session');
    }

    private function rejection(): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $this->resolver()->requireClaims();
            $this->fail('Expected requireClaims() to reject the request');
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    public function test_iams_own_401_is_passed_through_when_it_refuses_the_token(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Token is not active'], 401)]);
        $this->withBearer('token');

        $response = $this->rejection();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['message' => 'Token is not active'], $response->getData(true));
        $this->assertSame(ClaimsFailure::Rejected, $this->resolver()->failure());
    }

    public function test_iams_own_403_is_passed_through(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Forbidden'], 403)]);
        $this->withBearer('token');

        $response = $this->rejection();

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['message' => 'Forbidden'], $response->getData(true));
    }

    public function test_a_refusal_without_a_json_body_gets_the_standard_error_under_iams_status(): void
    {
        Http::fake(['*' => Http::sequence()->push('Forbidden', 403)->push('', 401)]);

        $this->withBearer('first');
        $forbidden = $this->rejection();
        $this->assertSame(403, $forbidden->getStatusCode());
        $this->assertSame('unauthorized_action', $forbidden->getData(true)['errors'][0]['title']);

        $this->withBearer('second');
        $unauthorized = $this->rejection();
        $this->assertSame(401, $unauthorized->getStatusCode());
        $this->assertSame('invalid_session', $unauthorized->getData(true)['errors'][0]['title']);
    }

    public function test_other_4xx_answers_get_the_standard_401(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Token not provided'], 400)]);
        $this->withBearer('token');

        $this->assertRejects(401, 'invalid_session');
        $this->assertSame(ClaimsFailure::Rejected, $this->resolver()->failure());
    }

    public function test_the_passed_through_refusal_is_cached_for_the_request(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Invalid token'], 401)]);
        $this->withBearer('token');

        $this->rejection();
        $this->assertSame(['message' => 'Invalid token'], $this->rejection()->getData(true));

        try {
            $this->resolver()->optionalClaims();
            $this->fail('Expected optionalClaims() to reject the request');
        } catch (HttpResponseException $e) {
            $this->assertSame(401, $e->getResponse()->getStatusCode());
        }

        Http::assertSentCount(1);
    }

    #[DataProvider('incompleteClaims')]
    public function test_it_rejects_with_401_when_iam_returns_incomplete_claims(array $body): void
    {
        Http::fake(['*' => Http::response($body)]);
        $this->withBearer('token');

        $this->assertRejects(401, 'invalid_session');
        $this->assertSame(ClaimsFailure::Rejected, $this->resolver()->failure());
    }

    public static function incompleteClaims(): array
    {
        return [
            'empty data' => [['data' => []]],
            'no data' => [['message' => 'ok']],
            'data not an array' => [['data' => 'nope']],
            'missing user_uuid' => [['data' => ['role_name' => 'student']]],
            'missing role_name' => [['data' => ['user_uuid' => 'user-1']]],
        ];
    }

    public function test_it_rejects_with_503_when_iam_errors(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $this->withBearer('token');

        $this->assertRejects(503, 'session_service_unavailable');
        $this->assertSame(ClaimsFailure::Unavailable, $this->resolver()->failure());
    }

    public function test_it_rejects_with_503_when_iam_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        $this->withBearer('token');

        $this->assertRejects(503, 'session_service_unavailable');
        $this->assertSame(ClaimsFailure::Unavailable, $this->resolver()->failure());
    }

    public function test_it_calls_iam_once_per_request_even_when_the_fetch_fails(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $this->withBearer('token');

        foreach (range(1, 3) as $ignored) {
            $this->resolver()->claims();
            $this->resolver()->hasClaims();
        }

        Http::assertSentCount(1);
    }

    public function test_each_request_resolves_its_own_claims(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->validClaims(['role_name' => 'student']))
            ->push($this->validClaims(['role_name' => 'teacher']))]);

        $this->withBearer('first');
        $this->assertSame('student', $this->resolver()->claims()->role_name);

        $this->withBearer('second');
        $this->assertSame('teacher', $this->resolver()->claims()->role_name);
    }

    public function test_error_detail_is_translated(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $this->withBearer('token');
        $this->app->setLocale('ar');

        try {
            $this->resolver()->requireClaims();
            $this->fail('Expected requireClaims() to reject the request');
        } catch (HttpResponseException $e) {
            $this->assertSame(
                'تعذر التحقق من الجلسة حاليا، برجاء المحاولة مرة أخرى بعد قليل',
                $e->getResponse()->getData(true)['errors'][0]['detail']
            );
        }
    }

    public function test_a_service_can_override_the_detail_translation(): void
    {
        // What lang/vendor/multi-tenant/en/iam.php in the service does
        $this->app['translator']->addLines(['iam.invalid_session' => 'service wording'], 'en', 'multi-tenant');
        $this->withBearer(null);

        try {
            $this->resolver()->requireClaims();
            $this->fail('Expected requireClaims() to reject the request');
        } catch (HttpResponseException $e) {
            $this->assertSame('service wording', $e->getResponse()->getData(true)['errors'][0]['detail']);
        }
    }
}
