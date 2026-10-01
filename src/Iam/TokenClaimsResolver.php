<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the current request's token claims from IAM.
 * Bound as scoped, so claims are fetched once per request (Octane safe).
 */
class TokenClaimsResolver
{
    private bool $resolved = false;

    private ?TokenClaims $claims = null;

    protected ?ClaimsFailure $failure = null;

    /**
     * IAM's own 401 / 403 answer, passed through to the client as-is.
     */
    private ?JsonResponse $rejection = null;

    /**
     * The claims, or null when they could not be resolved (see failure()).
     * A failed fetch is cached too, so a request never retries IAM.
     */
    public function claims(): ?TokenClaims
    {
        if (! $this->resolved) {
            $this->claims = $this->fetch();
            $this->resolved = true;
        }

        return $this->claims;
    }

    /**
     * Why claims() is null, or null when the claims resolved.
     */
    public function failure(): ?ClaimsFailure
    {
        $this->claims();

        return $this->failure;
    }

    public function hasClaims(): bool
    {
        return $this->claims() !== null;
    }

    /**
     * The claims, or stop the request: IAM's own 401 / 403 when it refused the
     * token, 401 when there is no token or the claims were unusable, 503 when
     * IAM was unreachable or errored.
     *
     * @throws HttpResponseException
     */
    public function requireClaims(): TokenClaims
    {
        $claims = $this->claims();
        if ($claims) {
            return $claims;
        }

        if ($this->rejection) {
            throw new HttpResponseException($this->rejection);
        }

        throw $this->failure === ClaimsFailure::Unavailable
            ? ErrorResponse::serviceUnavailable()
            : ErrorResponse::invalidSession();
    }

    /**
     * The claims, or null when the request carries no token (jobs, commands,
     * public routes). Any other failure stops the request with 401 / 503.
     *
     * @throws HttpResponseException
     */
    public function optionalClaims(): ?TokenClaims
    {
        if ($this->failure() === ClaimsFailure::MissingToken) {
            return null;
        }

        return $this->requireClaims();
    }

    protected function fetch(): ?TokenClaims
    {
        $token = request()->bearerToken();
        if (! $token) {
            $this->failure = ClaimsFailure::MissingToken;

            return null;
        }

        $url = IamConfig::url('token/claims');

        try {
            $response = Http::withToken($token)
                ->timeout(IamConfig::timeout())
                ->get($url);
        } catch (Exception $e) {
            Log::error('Failed to fetch token claims from IAM service', [
                'error' => $e->getMessage(),
                'service' => config('app.name'),
                'url' => $url,
            ]);
            $this->failure = ClaimsFailure::Unavailable;

            return null;
        }

        if (! $response->successful()) {
            Log::error('IAM service returned error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'service' => config('app.name'),
                'url' => $url,
            ]);
            $this->failure = $response->serverError()
                ? ClaimsFailure::Unavailable
                : ClaimsFailure::Rejected;
            $this->rejection = $this->passThrough($response);

            return null;
        }

        $data = $response->json('data');
        if (! is_array($data) || empty($data['user_uuid']) || empty($data['role_name'])) {
            // A 2xx without the identity claims would build a session with a null role
            Log::error('IAM service returned incomplete token claims', [
                'body' => $response->body(),
                'service' => config('app.name'),
                'url' => $url,
            ]);
            $this->failure = ClaimsFailure::Rejected;

            return null;
        }

        return new TokenClaims($data);
    }

    /**
     * IAM's 401 / 403 with its own body (e.g. "Token is not active"), so clients
     * can tell an expired token from a deactivated one. A body that is not a JSON
     * object gets the standard error under IAM's status. Other statuses: null.
     */
    private function passThrough(Response $response): ?JsonResponse
    {
        if (! in_array($response->status(), [401, 403], true)) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body) || $body === []) {
            $fallback = $response->status() === 403
                ? ErrorResponse::unauthorizedAction()
                : ErrorResponse::invalidSession();
            $body = $fallback->getResponse()->getData(true);
        }

        return new JsonResponse($body, $response->status());
    }
}
