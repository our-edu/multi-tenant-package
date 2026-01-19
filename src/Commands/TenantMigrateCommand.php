<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class TenantMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:migrate
                            {--rollback : Remove tenant_id column from tables}
                            {--table=* : Specific table(s) to migrate}';

    /**
     * The console command description.
     */
    protected $description = 'Add tenant_id column to configured tables';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tables = $this->getTables();

        if (empty($tables)) {
            $this->warn('No tables configured. Add tables to config/multi-tenant.php under "tables" key.');

            return self::SUCCESS;
        }

        $tenantColumn = config('multi-tenant.tenant_column', 'tenant_id');
        $isRollback = $this->option('rollback');

        $this->info($isRollback ? 'Removing tenant_id column...' : 'Adding tenant_id column...');
        $this->newLine();

        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;

        foreach ($tables as $table) {
            $result = $isRollback
                ? $this->removeColumn($table, $tenantColumn)
                : $this->addColumn($table, $tenantColumn);

            match ($result) {
                'success' => $successCount++,
                'skipped' => $skipCount++,
                'error' => $errorCount++,
            };
        }

        $this->newLine();
        $this->info("Done! Success: {$successCount}, Skipped: {$skipCount}, Errors: {$errorCount}");

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get tables to migrate.
     */
    protected function getTables(): array
    {
        $optionTables = $this->option('table');

        if (! empty($optionTables)) {
            return $optionTables;
        }

        $tables = config('multi-tenant.tables', []);

        // Return table names (keys) from the associative array
        return array_keys($tables);
    }

    /**
     * Add tenant_id column to a table.
     */
    protected function addColumn(string $table, string $column): string
    {
        if (! Schema::hasTable($table)) {
            $this->error("  ✗ Table '{$table}' does not exist");

            return 'error';
        }

        if (Schema::hasColumn($table, $column)) {
            $this->warn("  ⊘ Table '{$table}' already has '{$column}' column");

            return 'skipped';
        }

        try {
            Schema::table($table, function ($blueprint) use ($column) {
                $blueprint->unsignedBigInteger($column)->nullable()->index();
                $blueprint->foreign($column)->references('id')->on('tenants');
            });

            $this->info("  ✓ Added '{$column}' to '{$table}'");

            return 'success';
        } catch (Exception $e) {
            $this->error("  ✗ Failed to add '{$column}' to '{$table}': " . $e->getMessage());

            return 'error';
        }
    }

    /**
     * Remove tenant_id column from a table.
     */
    protected function removeColumn(string $table, string $column): string
    {
        if (! Schema::hasTable($table)) {
            $this->error("  ✗ Table '{$table}' does not exist");

            return 'error';
        }

        if (! Schema::hasColumn($table, $column)) {
            $this->warn("  ⊘ Table '{$table}' does not have '{$column}' column");

            return 'skipped';
        }

        try {
            Schema::table($table, function ($blueprint) use ($table, $column) {
                $blueprint->dropForeign([$column]);
                $blueprint->dropIndex([$column]);
                $blueprint->dropColumn($column);
            });

            $this->info("  ✓ Removed '{$column}' from '{$table}'");

            return 'success';
        } catch (Exception $e) {
            $this->error("  ✗ Failed to remove '{$column}' from '{$table}': " . $e->getMessage());

            return 'error';
        }
    }
}
