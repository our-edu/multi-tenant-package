<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam\Facades;

use Illuminate\Support\Facades\Facade;
use Ouredu\MultiTenant\Iam\ClaimsFailure;
use Ouredu\MultiTenant\Iam\PermissionAuthorizer;
use Ouredu\MultiTenant\Iam\Testing\FakePermissionAuthorizer;
use Ouredu\MultiTenant\Iam\Testing\FakeTokenClaimsResolver;
use Ouredu\MultiTenant\Iam\TokenClaims as Claims;
use Ouredu\MultiTenant\Iam\TokenClaimsResolver;

/**
 * @method static Claims|null claims()
 * @method static Claims requireClaims()
 * @method static Claims|null optionalClaims()
 * @method static ClaimsFailure|null failure()
 * @method static bool hasClaims()
 *
 * @see TokenClaimsResolver
 */
class TokenClaims extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TokenClaimsResolver::class;
    }

    /**
     * Never cache the resolver: it is per request, and a cached one would
     * serve the previous request's claims (Octane).
     */
    public static function getFacadeRoot(): TokenClaimsResolver
    {
        return static::$app->make(TokenClaimsResolver::class);
    }

    /**
     * Bind the service's session model (which uses HasTokenClaims) per request:
     * null when the request has no claims, otherwise built from them.
     *
     * @param class-string $sessionClass
     */
    public static function bindSession(string $sessionClass): void
    {
        static::$app->scoped($sessionClass, function ($app) use ($sessionClass) {
            $claims = $app->make(TokenClaimsResolver::class)->claims();

            return $claims ? $sessionClass::fromTokenClaims($claims) : null;
        });
    }

    /**
     * Resolve every request to these claims (merged over working defaults), without IAM.
     */
    public static function fake(array $claims = []): Claims
    {
        $fake = new Claims(array_merge([
            'user_uuid' => 'fake-user-uuid',
            'role_uuid' => 'fake-role-uuid',
            'role_name' => 'student',
            'branch' => '*',
            'user_branches' => ['*'],
            'is_valid' => true,
            'is_active' => true,
        ], $claims));

        static::swapResolver(new FakeTokenClaimsResolver($fake));

        return $fake;
    }

    /**
     * Make every request fail to resolve claims the given way.
     */
    public static function fakeFailure(ClaimsFailure $failure): void
    {
        static::swapResolver(new FakeTokenClaimsResolver(null, $failure));
    }

    /**
     * Allow only these "resource.action" permissions; ['*'] allows everything.
     *
     * @param string[] $allowed
     */
    public static function fakePermissions(array $allowed): void
    {
        static::$app->scoped(PermissionAuthorizer::class, fn () => new FakePermissionAuthorizer($allowed));
        static::$app->forgetScopedInstances();
    }

    private static function swapResolver(FakeTokenClaimsResolver $template): void
    {
        // A fresh copy per request scope, so each request resolves on its own
        static::$app->scoped(TokenClaimsResolver::class, fn () => clone $template);
        // Drop sessions already built from the real resolver
        static::$app->forgetScopedInstances();
    }
}
