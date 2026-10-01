<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

abstract class IamTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.iam_service_url', 'http://iam.test/api/v1/');
        $app['config']->set('multi-tenant.iam.register_middleware_aliases', true);
        // These tests exercise IAM alone; no tenant is resolved for their routes
        $app['config']->set('multi-tenant.middleware.enabled', false);
    }

    /**
     * Start a fresh request, so scoped instances (the resolver) are rebuilt.
     */
    protected function withBearer(?string $token): void
    {
        $request = Request::create('/anything');
        if ($token) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }
        $this->app->instance('request', $request);
        $this->app->forgetScopedInstances();
    }

    protected function validClaims(array $overrides = []): array
    {
        return [
            'data' => array_merge([
                'user_uuid' => 'user-1',
                'role_uuid' => 'role-1',
                'role_name' => 'student',
                'branch' => '*',
                'user_branches' => ['*'],
                'is_valid' => true,
                'tenant_id' => 1,
            ], $overrides),
        ];
    }
}
