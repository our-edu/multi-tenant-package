<?php

declare(strict_types=1);

namespace Tests\Commands;

use Tests\TestCase;

class TenantAddListenerTraitCommandTest extends TestCase
{
    public function test_command_fails_when_no_listeners_configured(): void
    {
        config(['multi-tenant.listeners' => []]);

        $this->artisan('tenant:add-listener-trait')
            ->expectsOutput('No listeners found.')
            ->assertExitCode(1);
    }

    public function test_command_warns_when_listener_class_not_found(): void
    {
        config(['multi-tenant.listeners' => [
            'App\\Listeners\\NonExistentListener',
        ]]);

        $this->artisan('tenant:add-listener-trait')
            ->expectsOutputToContain('Listener class not found')
            ->assertExitCode(1);
    }

    public function test_command_accepts_specific_listener_via_option(): void
    {
        // Since listener doesn't exist, it should fail
        $this->artisan('tenant:add-listener-trait', ['--listener' => ['App\\Listeners\\PaymentCreatedListener']])
            ->expectsOutputToContain('Listener class not found: App\\Listeners\\PaymentCreatedListener')
            ->assertExitCode(1);
    }

    public function test_command_dry_run_mode(): void
    {
        config(['multi-tenant.listeners' => [
            'App\\Listeners\\NonExistentListener',
        ]]);

        $this->artisan('tenant:add-listener-trait', ['--dry-run' => true])
            ->expectsOutput('Dry run mode - no files will be modified.')
            ->assertExitCode(1);
    }

    public function test_command_is_registered(): void
    {
        $this->assertTrue(
            collect($this->app['Illuminate\Contracts\Console\Kernel']->all())
                ->has('tenant:add-listener-trait')
        );
    }

    public function test_command_shows_usage_when_no_listeners(): void
    {
        config(['multi-tenant.listeners' => []]);

        $this->artisan('tenant:add-listener-trait')
            ->expectsOutput('Usage examples:')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_config_file_not_found(): void
    {
        $this->artisan('tenant:add-listener-trait', ['--config' => 'nonexistent_config_file'])
            ->expectsOutputToContain('Config file not found')
            ->assertExitCode(1);
    }

    public function test_command_reads_listeners_from_config_file(): void
    {
        // Create a temporary config file in Laravel's config directory
        $configName = 'test_sqs_events_' . uniqid();
        $configPath = config_path("{$configName}.php");
        file_put_contents($configPath, "<?php\nreturn [\n    'event.type' => 'App\\\\Listeners\\\\TestListener',\n];");

        try {
            // Since the class doesn't exist, it won't be included in the listeners array
            $this->artisan('tenant:add-listener-trait', ['--config' => $configName])
                ->expectsOutputToContain('Found 0 listener(s) in config file')
                ->assertExitCode(1);
        } finally {
            @unlink($configPath);
        }
    }

    public function test_command_reads_existing_listeners_from_config_file(): void
    {
        // Create a config file with an existing class (use a class from this package)
        $configName = 'test_sqs_events_' . uniqid();
        $configPath = config_path("{$configName}.php");
        $listenerClass = 'Ouredu\\MultiTenant\\Listeners\\TenantQueryListener';
        file_put_contents($configPath, "<?php\nreturn [\n    'event.type' => '{$listenerClass}',\n];");

        try {
            $this->artisan('tenant:add-listener-trait', ['--config' => $configName, '--dry-run' => true])
                ->expectsOutputToContain('Found 1 listener(s) in config file')
                ->assertExitCode(0);
        } finally {
            @unlink($configPath);
        }
    }
}

