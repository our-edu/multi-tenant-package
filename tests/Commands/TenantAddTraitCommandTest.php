<?php

declare(strict_types=1);

namespace Tests\Commands;

use Tests\TestCase;

class TenantAddTraitCommandTest extends TestCase
{
    public function test_command_fails_when_no_tables_configured(): void
    {
        config(['multi-tenant.tables' => []]);

        $this->artisan('tenant:add-trait')
            ->expectsOutput('No tables configured. Add tables to config/multi-tenant.php or use --table option.')
            ->assertExitCode(1);
    }

    public function test_command_warns_when_model_class_not_found(): void
    {
        config(['multi-tenant.tables' => [
            'nonexistent_table' => 'App\\Models\\NonExistentModel',
        ]]);

        $this->artisan('tenant:add-trait')
            ->expectsOutputToContain('Model class not found')
            ->assertExitCode(1);
    }

    public function test_command_filters_by_table_option(): void
    {
        config(['multi-tenant.tables' => [
            'users' => 'App\\Models\\User',
            'orders' => 'App\\Models\\Order',
        ]]);

        // Since neither model exists, it should fail but only process 'users'
        $this->artisan('tenant:add-trait', ['--table' => ['users']])
            ->expectsOutputToContain('Model class not found: App\\Models\\User')
            ->assertExitCode(1);
    }

    public function test_command_dry_run_mode(): void
    {
        config(['multi-tenant.tables' => [
            'nonexistent_table' => 'App\\Models\\NonExistentModel',
        ]]);

        $this->artisan('tenant:add-trait', ['--dry-run' => true])
            ->expectsOutput('Dry run mode - no files will be modified.')
            ->assertExitCode(1);
    }

    public function test_command_is_registered(): void
    {
        $this->assertTrue(
            collect($this->app['Illuminate\Contracts\Console\Kernel']->all())
                ->has('tenant:add-trait')
        );
    }
}
