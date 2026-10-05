<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam;

use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Asks IAM whether the current request's token holds a permission.
 * Bound as scoped, so each resource/action is asked once per request (Octane safe).
 */
class PermissionAuthorizer
{
    /** @var array<string, bool> */
    private array $decisions = [];

    /**
     * Denied when there is no token or IAM says no. IAM being unreachable or
     * erroring stops the request with 503 rather than passing it off as a denial.
     *
     * @throws HttpResponseException
     */
    public function allows(string $resource, string $action): bool
    {
        $key = "$resource.$action";

        return $this->decisions[$key] ??= $this->ask($resource, $action);
    }

    protected function ask(string $resource, string $action): bool
    {
        $token = IamClient::token();
        if (! $token) {
            return false;
        }

        $url = IamConfig::url('authorize');

        try {
            $response = IamClient::http($token)->post($url, [
                'token' => $token,
                'action' => $action,
                'resource' => $resource,
            ]);
        } catch (Exception $e) {
            IamClient::logError('Failed to authorize permission with IAM service', $url, [
                'error' => $e->getMessage(),
                'permission' => "$resource.$action",
            ]);

            throw ErrorResponse::serviceUnavailable();
        }

        if ($response->serverError()) {
            IamClient::logError('IAM service returned error while authorizing permission', $url, [
                'status' => $response->status(),
                'body' => $response->body(),
                'permission' => "$resource.$action",
            ]);

            throw ErrorResponse::serviceUnavailable();
        }

        return $response->successful() && (bool) $response->json('authorized', false);
    }
}
