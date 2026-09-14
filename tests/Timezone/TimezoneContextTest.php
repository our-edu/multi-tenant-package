<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Timezone;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Mockery;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Ouredu\MultiTenant\Timezone\TimezoneContext;
use Tests\Support\CreatesTimezoneTables;
use Tests\Support\FakeSession;
use Tests\TestCase;

/**
 * TimezoneContext::current() — session claim → tenant context → default.
 */
class TimezoneContextTest extends TestCase
{
    use CreatesTimezoneTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTimezoneTables();
        $this->useAppTimezone('Asia/Riyadh');
    }

    protected function tearDown(): void
    {
        $this->tearDownTimezoneTables();

        parent::tearDown();
    }

    public function testSessionClaimWins(): void
    {
        FakeSession::$session = (object) ['timezone' => 'Africa/Cairo', 'branch_uuid' => $this->riyadhBranch, 'tenant_id' => 2];
        app(TenantContext::class)->setTenantId(2);

        $this->assertSame('Africa/Cairo', TenantTimezone::current());
    }

    public function testInvalidSessionClaimFallsThroughToTenantLookup(): void
    {
        FakeSession::$session = (object) ['timezone' => '+03:00', 'branch_uuid' => '*'];
        app(TenantContext::class)->setTenantId(1);

        $this->assertSame('Africa/Cairo', TenantTimezone::current());
    }

    public function testSessionBranchIsUsedWhenClaimIsMissing(): void
    {
        FakeSession::$session = (object) ['branch_uuid' => $this->cairoBranch];
        app(TenantContext::class)->setTenantId(1);

        $this->assertSame('Europe/London', TenantTimezone::current());
    }

    public function testJobPathUsesTenantContextWithoutSession(): void
    {
        FakeSession::reset();
        app(TenantContext::class)->setTenantId(1);

        $this->assertSame('Africa/Cairo', TenantTimezone::current());
    }

    public function testNothingResolvableUsesAppTimezone(): void
    {
        FakeSession::reset();

        $this->assertSame('Asia/Riyadh', TenantTimezone::current());

        config()->set('multi-tenant.timezone.default', 'Africa/Cairo');
        $this->app->forgetScopedInstances();

        $this->assertSame('Africa/Cairo', TenantTimezone::current());
    }

    public function testSessionIsSkippedInConsoleOutsideUnitTests(): void
    {
        FakeSession::$session = (object) ['timezone' => 'Europe/London'];
        app(TenantContext::class)->setTenantId(1);

        $app = Mockery::mock(Application::class);
        $app->shouldReceive('runningInConsole')->andReturn(true);
        $app->shouldReceive('runningUnitTests')->andReturn(false);
        $app->shouldReceive('make')->with(TenantContext::class)->andReturn(app(TenantContext::class));

        $context = new TimezoneContext($app);

        // Session (London) ignored: cron/queue path resolves the tenant zone from the DB.
        $this->assertSame('Africa/Cairo', $context->current());
    }

    public function testCurrentIsMemoized(): void
    {
        FakeSession::$session = (object) ['timezone' => 'Africa/Cairo'];

        $this->assertSame('Africa/Cairo', TenantTimezone::current());

        FakeSession::$session = (object) ['timezone' => 'Europe/London'];

        $this->assertSame('Africa/Cairo', TenantTimezone::current());

        app(TimezoneContext::class)->clear();

        $this->assertSame('Europe/London', TenantTimezone::current());
    }

    public function testSetOverridesAndValidates(): void
    {
        TenantTimezone::set('Africa/Cairo');
        $this->assertSame('Africa/Cairo', TenantTimezone::current());

        TenantTimezone::set(null);
        $this->assertSame('Asia/Riyadh', TenantTimezone::current());

        $this->expectException(InvalidArgumentException::class);
        TenantTimezone::set('Etc/GMT-2');
    }

    public function testRunInRestoresPreviousState(): void
    {
        TenantTimezone::set('Asia/Riyadh');

        $inside = TenantTimezone::runIn('Africa/Cairo', fn () => TenantTimezone::current());

        $this->assertSame('Africa/Cairo', $inside);
        $this->assertSame('Asia/Riyadh', TenantTimezone::current());
    }

    public function testStorageIsAppTimezone(): void
    {
        $this->assertSame('Asia/Riyadh', TenantTimezone::storage());

        $this->useAppTimezone('UTC');
        $this->assertSame('UTC', TenantTimezone::storage());
    }
}
