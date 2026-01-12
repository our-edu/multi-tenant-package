<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base TestCase for all multi-tenant package tests
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected Container $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container();
        Container::setInstance($this->app);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }
}

