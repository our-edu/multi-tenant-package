<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ouredu\MultiTenant\Iam\IamClient;

class IamClientTest extends IamTestCase
{
    public function test_token_is_null_without_a_bearer_token(): void
    {
        $this->withBearer(null);

        $this->assertNull(IamClient::token());
    }

    public function test_token_reads_the_bearer_token(): void
    {
        $this->withBearer('token');

        $this->assertSame('token', IamClient::token());
    }

    public function test_http_sends_the_token(): void
    {
        Http::fake();

        IamClient::http('token')->get('http://iam.test/api/v1/anything');

        Http::assertSent(fn (ClientRequest $request) => $request->hasHeader('Authorization', 'Bearer token'));
    }

    public function test_log_error_adds_the_service_and_url(): void
    {
        config(['app.name' => 'communication']);

        IamClient::logError('IAM is down', 'http://iam.test/api/v1/token/claims', ['status' => 500]);

        Log::shouldHaveReceived('error')->once()->with('IAM is down', [
            'status' => 500,
            'service' => 'communication',
            'url' => 'http://iam.test/api/v1/token/claims',
        ]);
        // Mockery's expectation is not counted by PHPUnit
        $this->addToAssertionCount(1);
    }
}
