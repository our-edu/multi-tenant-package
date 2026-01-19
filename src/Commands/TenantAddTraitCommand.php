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
 * TenantAddTraitCommand
 *
 * Artisan command to automatically add HasTenant trait to models
 * based on the tables configured in the 'tables' config array.
 */
class TenantAddTraitCommand extends Command
{
    protected $signature = 'tenant:add-trait
                            {--table=* : Specific tables to process (optional)}
                            {--dry-run : Show what would be changed without modifying files}';

    protected $description = 'Add HasTenant trait to models based on configured tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configTables = config('multi-tenant.tables', []);
        $specificTables = $this->option('table');

        // Filter tables if specific ones are requested
        if (! empty($specificTables)) {
            $tables = array_filter(
                $configTables,
                fn ($model, $table) => in_array($table, $specificTables, true),
                ARRAY_FILTER_USE_BOTH
            );
        } else {
            $tables = $configTables;
        }

        if (empty($tables)) {
            $this->error('No tables configured. Add tables to config/multi-tenant.php or use --table option.');

            return self::FAILURE;
        }

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Dry run mode - no files will be modified.');
            $this->newLine();
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($tables as $table => $modelClass) {
            $result = $this->processModel($table, $modelClass, $isDryRun);

            match ($result) {
                'processed' => $processed++,
                'skipped' => $skipped++,
                'failed' => $failed++,
            };
        }

        $this->newLine();
        $this->info("Summary: {$processed} processed, {$skipped} skipped, {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Process a single model.
     */
    protected function processModel(string $table, string $modelClass, bool $isDryRun): string
    {
        if (! class_exists($modelClass)) {
            $this->warn("Model class not found: {$modelClass} (table: {$table})");

            return 'failed';
        }

        $modelPath = $this->getModelPath($modelClass);

        if (! $modelPath || ! file_exists($modelPath)) {
            $this->warn("Could not find model file for: {$modelClass}");

            return 'failed';
        }

        $content = file_get_contents($modelPath);

        // Check if trait is already added
        if ($this->hasTraitAlready($content)) {
            $this->line("  <comment>Skipped</comment> {$modelClass} - HasTenant trait already exists");

            return 'skipped';
        }

        if ($isDryRun) {
            $this->line("  <info>Would add</info> HasTenant trait to {$modelClass}");

            return 'processed';
        }

        // Add the trait
        $newContent = $this->addTraitToModel($content);

        if ($newContent === $content) {
            $this->warn("Could not add trait to: {$modelClass}");

            return 'failed';
        }

        file_put_contents($modelPath, $newContent);
        $this->line("  <info>Added</info> HasTenant trait to {$modelClass}");

        return 'processed';
    }

    /**
     * Get the file path for a model class.
     */
    protected function getModelPath(string $modelClass): ?string
    {
        try {
            $reflection = new ReflectionClass($modelClass);

            return $reflection->getFileName();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check if the model already has HasTenant trait.
     */
    protected function hasTraitAlready(string $content): bool
    {
        // Check for use statement
        if (preg_match('/use\s+.*HasTenant\s*[;,]/', $content)) {
            return true;
        }

        // Check for trait usage in class
        if (preg_match('/use\s+HasTenant\s*[;,]/', $content)) {
            return true;
        }

        return false;
    }

    /**
     * Add HasTenant trait to the model content.
     */
    protected function addTraitToModel(string $content): string
    {
        $useStatement = 'use Ouredu\\MultiTenant\\Traits\\HasTenant;';
        $traitUsage = 'use HasTenant;';

        // Add use statement after namespace
        if (! str_contains($content, $useStatement)) {
            // Find the last use statement or after namespace
            if (preg_match('/^(namespace\s+[^;]+;\s*\n)((?:use\s+[^;]+;\s*\n)*)/m', $content, $matches)) {
                $namespace = $matches[1];
                $existingUses = $matches[2];

                $newUses = $existingUses . $useStatement . "\n";
                $content = str_replace($namespace . $existingUses, $namespace . $newUses, $content);
            }
        }

        // Add trait usage inside class
        if (! preg_match('/use\s+HasTenant\s*[;,]/', $content)) {
            // Find class opening and add trait
            if (preg_match('/(class\s+\w+[^{]*\{)(\s*\n)/', $content, $matches)) {
                $classOpening = $matches[1];
                $whitespace = $matches[2];

                $replacement = $classOpening . $whitespace . "    {$traitUsage}\n";
                $content = preg_replace('/(class\s+\w+[^{]*\{)(\s*\n)/', $replacement, $content, 1);
            }
        }

        return $content;
    }
}
