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

if (! function_exists('fake_rejecting_session')) {
    /**
     * A session helper that throws, like a service's getSession() when IAM refuses the token.
     */
    function fake_rejecting_session(): ?object
    {
        throw new RuntimeException('IAM refused the token');
    }
}
