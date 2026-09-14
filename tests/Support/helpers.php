<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

use Tests\Support\FakeSession;

if (! function_exists('fake_timezone_session')) {
    /**
     * Global session helper registered as multi-tenant.session.helper in timezone tests.
     */
    function fake_timezone_session(): ?object
    {
        return FakeSession::$session;
    }
}
