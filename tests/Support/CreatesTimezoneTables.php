<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * In-memory `tenants` / `branches` tables shaped like the shared ouredu-db.
 *
 * Tenant 1 = Cairo (Africa/Cairo), tenant 2 = Riyadh (Asia/Riyadh), tenant 3 has an
 * invalid stored zone. Branch uuids are exposed as properties.
 */
trait CreatesTimezoneTables
{
    protected string $cairoBranch;

    protected string $cairoBranchNoZone;

    protected string $cairoBranchDeleted;

    protected string $cairoBranchInvalidZone;

    protected string $riyadhBranch;

    protected function setUpTimezoneTables(): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('multi-tenant.session.helper', 'fake_timezone_session');
        config()->set('multi-tenant.query_listener.enabled', false);

        Schema::connection('testing')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone', 64)->nullable();
        });

        Schema::connection('testing')->create('branches', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        DB::connection('testing')->table('tenants')->insert([
            ['id' => 1, 'name' => 'Cairo School', 'timezone' => 'Africa/Cairo'],
            ['id' => 2, 'name' => 'Riyadh School', 'timezone' => 'Asia/Riyadh'],
            ['id' => 3, 'name' => 'Broken School', 'timezone' => '+03:00'],
        ]);

        $this->cairoBranch = Str::uuid()->toString();
        $this->cairoBranchNoZone = Str::uuid()->toString();
        $this->cairoBranchDeleted = Str::uuid()->toString();
        $this->cairoBranchInvalidZone = Str::uuid()->toString();
        $this->riyadhBranch = Str::uuid()->toString();

        DB::connection('testing')->table('branches')->insert([
            ['uuid' => $this->cairoBranch, 'tenant_id' => 1, 'timezone' => 'Europe/London', 'deleted_at' => null],
            ['uuid' => $this->cairoBranchNoZone, 'tenant_id' => 1, 'timezone' => null, 'deleted_at' => null],
            ['uuid' => $this->cairoBranchDeleted, 'tenant_id' => 1, 'timezone' => 'Asia/Dubai', 'deleted_at' => '2026-01-01 00:00:00'],
            ['uuid' => $this->cairoBranchInvalidZone, 'tenant_id' => 1, 'timezone' => 'Etc/GMT-2', 'deleted_at' => null],
            ['uuid' => $this->riyadhBranch, 'tenant_id' => 2, 'timezone' => 'Asia/Qatar', 'deleted_at' => null],
        ]);
    }

    protected function tearDownTimezoneTables(): void
    {
        Schema::connection('testing')->dropIfExists('branches');
        Schema::connection('testing')->dropIfExists('tenants');
        FakeSession::reset();
    }
}
