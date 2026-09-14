<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Support;

/**
 * Holder for the object returned by the global `fake_timezone_session()` helper
 * (declared in tests/Support/helpers.php) used as multi-tenant.session.helper in timezone tests.
 */
final class FakeSession
{
    public static ?object $session = null;

    public static function reset(): void
    {
        self::$session = null;
    }
}
