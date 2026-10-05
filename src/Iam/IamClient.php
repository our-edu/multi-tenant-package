<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * How the IAM module calls IAM: the request's Bearer token, the configured
 * timeout, and one log context. Each caller decides what a failure means.
 */
final class IamClient
{
    /**
     * The current request's Bearer token, or null when it has none.
     */
    public static function token(): ?string
    {
        return request()->bearerToken() ?: null;
    }

    /**
     * A request to IAM on behalf of the token, with the configured timeout.
     */
    public static function http(string $token): PendingRequest
    {
        return Http::withToken($token)->timeout(IamConfig::timeout());
    }

    /**
     * Log an IAM failure, tagged with the calling service and the IAM endpoint.
     *
     * @param array<string, mixed> $context
     */
    public static function logError(string $message, string $url, array $context = []): void
    {
        Log::error($message, $context + [
            'service' => config('app.name'),
            'url' => $url,
        ]);
    }
}
