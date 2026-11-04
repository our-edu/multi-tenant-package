<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Ouredu\MultiTenant\Resolvers\DomainTenantResolver;
use ReflectionMethod;
use Tests\TestCase;

class DomainTenantResolverTestTenant extends Model
{
    protected $table = 'tenants';

    protected $casts = ['clients' => 'array'];
}

class DomainTenantResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('multi-tenant.tenant_model', DomainTenantResolverTestTenant::class);
        config()->set('multi-tenant.domain.routes', ['*/applications/terms-and-conditions', 'public.page']);

        Schema::connection('testing')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('clients')->nullable();
        });

        DB::connection('testing')->table('tenants')->insert([
            ['id' => 12, 'domain' => 'baraem.school.example.net', 'is_active' => true,
                'clients' => json_encode(['website' => ['domain' => 'baraem.website.example.net', 'is_active' => true]])],
            ['id' => 13, 'domain' => 'shorouk.school.example.net', 'is_active' => true,
                'clients' => json_encode(['dashboard' => ['domain' => 'shroug.school.example.net', 'is_active' => true]])],
            ['id' => 14, 'domain' => 'closed.school.example.net', 'is_active' => false, 'clients' => null],
            ['id' => 15, 'domain' => 'other.school.example.net', 'is_active' => true,
                'clients' => json_encode(['website' => ['domain' => 'old.website.example.net', 'is_active' => false]])],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection('testing')->dropIfExists('tenants');

        parent::tearDown();
    }

    private function resolveFor(string $path, array $headers = [], ?string $routeName = null): ?int
    {
        $request = Request::create('https://api.example.net/' . ltrim($path, '/'), 'GET');

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        if ($routeName !== null) {
            $route = (new Route('GET', ltrim($path, '/'), []))->name($routeName);
            $request->setRouteResolver(fn () => $route);
        }

        $this->app->instance('request', $request);

        return (new DomainTenantResolver())->resolveTenantId();
    }

    public function testResolvesTenantFromOriginOnAllowedRoute(): void
    {
        $this->assertSame(13, $this->resolveFor('admission/applications/terms-and-conditions', [
            'Origin' => 'https://shorouk.school.example.net',
        ]));
    }

    public function testFallsBackToRefererWhenNoOrigin(): void
    {
        $this->assertSame(12, $this->resolveFor('admission/applications/terms-and-conditions', [
            'Referer' => 'https://baraem.school.example.net/applications?x=1',
        ]));
    }

    public function testHostIsLowercasedAndPortIgnored(): void
    {
        $this->assertSame(12, $this->resolveFor('admission/applications/terms-and-conditions', [
            'Origin' => 'https://BARAEM.School.Example.Net:8443',
        ]));
    }

    public function testMatchesDomainsListedInClientsJson(): void
    {
        $this->assertSame(12, $this->resolveFor('admission/applications/terms-and-conditions', [
            'Referer' => 'https://baraem.website.example.net/',
        ]));
        $this->assertSame(13, $this->resolveFor('admission/applications/terms-and-conditions', [
            'Referer' => 'https://shroug.school.example.net/',
        ]));
    }

    public function testMatchesAllowedRouteByName(): void
    {
        $this->assertSame(12, $this->resolveFor('somewhere/else', [
            'Origin' => 'https://baraem.school.example.net',
        ], 'public.page'));
    }

    public function testReturnsNullOnRoutesNotListed(): void
    {
        $this->assertNull($this->resolveFor('admission/applications/students', [
            'Origin' => 'https://shorouk.school.example.net',
        ]));
    }

    public function testDisabledWhenNoRoutesConfigured(): void
    {
        config()->set('multi-tenant.domain.routes', []);

        $this->assertNull($this->resolveFor('admission/applications/terms-and-conditions', [
            'Origin' => 'https://shorouk.school.example.net',
        ]));
    }

    public function testInactiveTenantsAndClientsDoNotMatch(): void
    {
        $this->assertNull($this->resolveFor('admission/applications/terms-and-conditions', [
            'Origin' => 'https://closed.school.example.net',
        ]));
        $this->assertNull($this->resolveFor('admission/applications/terms-and-conditions', [
            'Origin' => 'https://old.website.example.net',
        ]));
    }

    public function testUnknownOrMissingHostReturnsNull(): void
    {
        $this->assertNull($this->resolveFor('admission/applications/terms-and-conditions', [
            'Origin' => 'https://unknown.example.net',
        ]));
        $this->assertNull($this->resolveFor('admission/applications/terms-and-conditions'));
    }

    public function testRequestHostIsNotUsedByDefault(): void
    {
        DB::connection('testing')->table('tenants')->insert(['id' => 16, 'domain' => 'api.example.net', 'is_active' => true]);

        $this->assertNull($this->resolveFor('admission/applications/terms-and-conditions'));

        config()->set('multi-tenant.domain.sources', ['origin', 'referer', 'host']);
        $this->assertSame(16, $this->resolveFor('admission/applications/terms-and-conditions'));
    }

    public function testReturnsNullWhenTenantModelIsMissing(): void
    {
        config()->set('multi-tenant.tenant_model', 'App\\Models\\DoesNotExist');

        $this->assertNull($this->resolveFor('admission/applications/terms-and-conditions', [
            'Origin' => 'https://shorouk.school.example.net',
        ]));
    }

    public function testChainUsesDomainResolverLast(): void
    {
        $method = new ReflectionMethod(ChainTenantResolver::class, 'getDefaultResolvers');
        $resolvers = $method->invoke(new ChainTenantResolver());

        $this->assertInstanceOf(DomainTenantResolver::class, end($resolvers));
    }
}
