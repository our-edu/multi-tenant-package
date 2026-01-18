<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Commands;

use Ouredu\MultiTenant\Commands\TenantMigrateCommand;
use Tests\TestCase;

class TenantMigrateCommandTest extends TestCase
{
    public function testCommandIsRegistered(): void
    {
        $this->assertTrue(
            $this->app->make('Illuminate\Contracts\Console\Kernel')
                ->all()['tenant:migrate'] instanceof TenantMigrateCommand
        );
    }

    public function testCommandShowsWarningWhenNoTablesConfigured(): void
    {
        config(['multi-tenant.tables' => []]);

        $this->artisan('tenant:migrate')
            ->expectsOutput('No tables configured. Add tables to config/multi-tenant.php under "tables" key.')
            ->assertSuccessful();
    }

    public function testCommandHasRollbackOption(): void
    {
        $command = $this->app->make(TenantMigrateCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('rollback'));
    }

    public function testCommandHasTableOption(): void
    {
        $command = $this->app->make(TenantMigrateCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('table'));
    }
}
