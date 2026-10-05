<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Illuminate\Routing\Router;
use Ouredu\MultiTenant\Iam\IamConfig;
use RuntimeException;
use Tests\TestCase;

class IamConfigTest extends TestCase
{
    public function testDefaultsApplyWithoutAnIamBlock(): void
    {
        config(['multi-tenant.iam' => null]);

        $this->assertSame(10, IamConfig::timeout());
        $this->assertFalse(IamConfig::get('register_middleware_aliases'));
    }

    public function testAPartialIamBlockKeepsTheOtherDefaults(): void
    {
        // What a service's published config would hold after adding one key
        config(['multi-tenant.iam' => ['timeout' => 3]]);

        $this->assertSame(3, IamConfig::timeout());
        $this->assertFalse(IamConfig::get('register_middleware_aliases'));
        $this->assertNull(IamConfig::get('guard'));
    }

    public function testTheUrlIsTheServicesIamUrl(): void
    {
        config(['app.iam_service_url' => 'http://saas-iam-service:7777/iam/api/v1/']);

        $this->assertSame('http://saas-iam-service:7777/iam/api/v1/token/claims', IamConfig::url('token/claims'));
    }

    public function testAMissingServiceIamUrlIsAConfigurationError(): void
    {
        config(['app.iam_service_url' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('app.iam_service_url');

        IamConfig::url('token/claims');
    }

    public function testMiddlewareAliasesAreNotRegisteredByDefault(): void
    {
        $aliases = $this->app->make(Router::class)->getMiddleware();

        $this->assertArrayNotHasKey('role', $aliases);
        $this->assertArrayNotHasKey('permission', $aliases);
    }

    public function testTheMessagesAreTranslated(): void
    {
        $this->app->setLocale('ar');

        $this->assertSame('الجلسة غير صالحة برجاء اعادة تسجيل الدخول', trans('multi-tenant::iam.invalid_session'));
    }
}
