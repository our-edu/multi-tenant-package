<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Base TestCase for all multi-tenant package tests
 */
abstract class TestCase extends PHPUnit\Framework\TestCase
{
    protected Container $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->app = null;
    }
}

