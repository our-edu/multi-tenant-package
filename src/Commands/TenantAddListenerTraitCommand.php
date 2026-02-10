<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Commands;

use Illuminate\Console\Command;
use ReflectionClass;
use Throwable;

/**
 * TenantAddListenerTraitCommand
 *
 * Artisan command to automatically add SetsTenantFromPayload trait to listener classes.
 * Listeners can be provided via:
 * - A config file path (e.g., sqs_events.php, EventServiceProvider-like files)
 * - The 'listeners' config array in multi-tenant.php
 * - Direct class specification via --listener option
 */
class TenantAddListenerTraitCommand extends Command
{
    protected $signature = 'tenant:add-listener-trait
                            {--config= : Config file name (e.g., sqs_events) - will look in config directory}
                            {--listener=* : Specific listener classes to process (optional)}
                            {--dry-run : Show what would be changed without modifying files}';

    protected $description = 'Add SetsTenantFromPayload trait to listeners from config file or configured listeners';

    /**
     * The trait use statement to add.
     */
    protected string $traitUseStatement = 'use Ouredu\\MultiTenant\\Traits\\SetsTenantFromPayload;';

    /**
     * The trait usage inside the class.
     */
    protected string $traitUsage = 'use SetsTenantFromPayload;';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configName = $this->option('config');
        $specificListeners = $this->option('listener');

        // Resolve config path from config name
        $configPath = $configName ? $this->resolveConfigPath($configName) : null;

        // Get listeners from config file path or package config
        $listeners = $this->resolveListeners($configPath, $specificListeners);

        if (empty($listeners)) {
            $this->error('No listeners found.');
            $this->newLine();
            $this->info('Usage examples:');
            $this->line('  # From a config file (by name):');
            $this->line('  php artisan tenant:add-listener-trait --config=sqs_events');
            $this->newLine();
            $this->line('  # From multi-tenant.php config:');
            $this->line("  Add listeners to config/multi-tenant.php under 'listeners' key");
            $this->newLine();
            $this->line('  # Specific listener class:');
            $this->line('  php artisan tenant:add-listener-trait --listener="App\\Listeners\\MyListener"');

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Dry run mode - no files will be modified.');
            $this->newLine();
        }

        $this->info('Processing ' . count($listeners) . ' listener(s)...');
        $this->newLine();

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($listeners as $listenerClass) {
            $result = $this->processListener($listenerClass, $isDryRun);

            match ($result) {
                'processed' => $processed++,
                'skipped' => $skipped++,
                'failed' => $failed++,
            };
        }

        $this->newLine();
        $this->info("Summary: {$processed} processed, {$skipped} skipped, {$failed} failed");

        return $failed > 0 && $processed === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve config file path from config name.
     *
     * @param string $configName
     * @return string
     */
    protected function resolveConfigPath(string $configName): string
    {
        // Remove .php extension if provided
        $configName = preg_replace('/\.php$/', '', $configName);

        // Get Laravel's config path
        $configPath = config_path("{$configName}.php");

        return $configPath;
    }

    /**
     * Resolve listeners from config file or package config.
     *
     * @param string|null $configPath
     * @param array $specificListeners
     * @return array
     */
    protected function resolveListeners(?string $configPath, array $specificListeners): array
    {
        $listeners = [];

        // If specific listeners provided via --listener option
        if (! empty($specificListeners)) {
            return $specificListeners;
        }

        // If config file path provided
        if ($configPath) {
            $listeners = $this->extractListenersFromConfigFile($configPath);
        } else {
            // Fall back to multi-tenant.php config
            $listeners = config('multi-tenant.listeners', []);
        }

        return array_unique(array_filter($listeners));
    }

    /**
     * Extract listener classes from a config file.
     *
     * Supports formats like:
     * - sqs_events.php: ['event.type' => ListenerClass::class]
     * - EventServiceProvider-style: ['Event' => [ListenerClass::class]]
     * - Simple array: [ListenerClass::class, ...]
     *
     * @param string $configPath
     * @return array
     */
    protected function extractListenersFromConfigFile(string $configPath): array
    {
        if (! file_exists($configPath)) {
            $this->error("Config file not found: {$configPath}");
            return [];
        }

        $config = require $configPath;

        if (! is_array($config)) {
            $this->error("Config file must return an array: {$configPath}");
            return [];
        }

        $listeners = [];

        foreach ($config as $key => $value) {
            if (is_string($value) && class_exists($value)) {
                // Format: 'event.type' => ListenerClass::class
                $listeners[] = $value;
            } elseif (is_array($value)) {
                // Format: 'Event' => [ListenerClass::class, ...] (EventServiceProvider style)
                foreach ($value as $listener) {
                    if (is_string($listener) && class_exists($listener)) {
                        $listeners[] = $listener;
                    }
                }
            }
        }

        $this->info("Found " . count($listeners) . " listener(s) in config file: {$configPath}");

        return $listeners;
    }

    /**
     * Process a single listener class.
     */
    protected function processListener(string $listenerClass, bool $isDryRun): string
    {
        if (! class_exists($listenerClass)) {
            $this->warn("  <fg=red>✗</> Listener class not found: {$listenerClass}");

            return 'failed';
        }

        $listenerPath = $this->getListenerPath($listenerClass);

        if (! $listenerPath || ! file_exists($listenerPath)) {
            $this->warn("  <fg=red>✗</> Could not find listener file for: {$listenerClass}");

            return 'failed';
        }

        $content = file_get_contents($listenerPath);

        // Check if trait is already added
        if ($this->hasTraitAlready($content)) {
            $this->line("  <comment>⊘ Skipped</comment> {$listenerClass} - SetsTenantFromPayload trait already exists");

            return 'skipped';
        }

        if ($isDryRun) {
            $this->line("  <info>→ Would add</info> SetsTenantFromPayload trait to {$listenerClass}");

            return 'processed';
        }

        // Add the trait
        $newContent = $this->addTraitToListener($content);

        if ($newContent === $content) {
            $this->warn("  <fg=red>✗</> Could not add trait to: {$listenerClass}");

            return 'failed';
        }

        file_put_contents($listenerPath, $newContent);
        $this->line("  <info>✓ Added</info> SetsTenantFromPayload trait to {$listenerClass}");

        return 'processed';
    }

    /**
     * Get the file path for a listener class.
     */
    protected function getListenerPath(string $listenerClass): ?string
    {
        try {
            $reflection = new ReflectionClass($listenerClass);

            return $reflection->getFileName();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check if the listener already has SetsTenantFromPayload trait.
     */
    protected function hasTraitAlready(string $content): bool
    {
        // Check for use statement (import)
        if (preg_match('/use\s+.*SetsTenantFromPayload\s*[;,]/', $content)) {
            return true;
        }

        // Check for trait usage in class body
        if (preg_match('/{\s*\n\s*use\s+SetsTenantFromPayload\s*[;,]/', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Add SetsTenantFromPayload trait to the listener content.
     */
    protected function addTraitToListener(string $content): string
    {
        // Add use statement after namespace
        if (! str_contains($content, $this->traitUseStatement)) {
            // Find the last use statement or after namespace
            if (preg_match('/^(namespace\s+[^;]+;\s*\n)((?:use\s+[^;]+;\s*\n)*)/m', $content, $matches)) {
                $namespace = $matches[1];
                $existingUses = $matches[2];

                $newUses = $existingUses . $this->traitUseStatement . "\n";
                $content = str_replace($namespace . $existingUses, $namespace . $newUses, $content);
            }
        }

        // Add trait usage inside class
        if (! preg_match('/{\s*\n\s*use\s+SetsTenantFromPayload\s*[;,]/', $content)) {
            // Check if class already has other traits being used
            if (preg_match('/(class\s+\w+[^{]*\{\s*\n)(\s*use\s+[^;]+;)/', $content, $matches)) {
                // Class has existing traits, add to the list
                $classOpeningWithFirstTrait = $matches[0];
                $existingTraitLine = $matches[2];

                // Check if it's a single trait or multiple traits
                if (str_contains($existingTraitLine, ',')) {
                    // Multiple traits on same line, append before semicolon
                    $newTraitLine = rtrim($existingTraitLine, ';') . ', SetsTenantFromPayload;';
                } else {
                    // Single trait, add on same line
                    $newTraitLine = rtrim($existingTraitLine, ';') . ', SetsTenantFromPayload;';
                }

                $content = str_replace($existingTraitLine, $newTraitLine, $content);
            } elseif (preg_match('/(class\s+\w+[^{]*\{)(\s*\n)/', $content, $matches)) {
                // No existing traits, add new trait line
                $classOpening = $matches[1];
                $whitespace = $matches[2];

                $replacement = $classOpening . $whitespace . "    {$this->traitUsage}\n";
                $content = preg_replace('/(class\s+\w+[^{]*\{)(\s*\n)/', $replacement, $content, 1);
            }
        }

        return $content;
    }
}

