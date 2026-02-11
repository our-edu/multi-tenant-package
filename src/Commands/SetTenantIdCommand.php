<?php

declare(strict_types=1);

namespace Ouredu\MultiTenant\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command as CommandAlias;

class SetTenantIdCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:set-id
                            {--table= : Specific table to update (optional, updates all if not specified)}
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set tenant_id for all rows in tenant tables using the active tenant from tenants table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificTable = $this->option('table');
        $dryRun = $this->option('dry-run');
        $tenantTable = config('multi-tenant.tenants_table', 'tenants');
        $tenantColumn = config('multi-tenant.tenant_column', 'tenant_id');
        $tables = config('multi-tenant.tables', []);

        // Get active tenant from database
        $activeTenant = $tenantTable::where('is_active', true)->first();

        if (! $activeTenant) {
            $this->error('No active tenant found in tenants table.');

            return CommandAlias::FAILURE;
        }

        $tenantId = $activeTenant->id;
        $this->info("Found active tenant: {$activeTenant->name} (ID: {$tenantId})");
        $this->newLine();

        if (empty($tables)) {
            $this->error('No tables configured in multi-tenant.tables config.');

            return CommandAlias::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made.');
            $this->newLine();
        }

        $this->info("Setting {$tenantColumn} = {$tenantId} for all rows in tenant tables...");
        $this->newLine();

        $totalUpdated = 0;
        $results = [];

        foreach ($tables as $tableName => $modelClass) {
            // Skip if specific table is requested and this is not it
            if ($specificTable && $tableName !== $specificTable) {
                continue;
            }

            // Check if table exists
            if (! Schema::hasTable($tableName)) {
                $this->warn("Table '{$tableName}' does not exist. Skipping...");

                continue;
            }

            // Check if tenant column exists
            if (! Schema::hasColumn($tableName, $tenantColumn)) {
                $this->warn("Table '{$tableName}' does not have '{$tenantColumn}' column. Skipping...");

                continue;
            }

            // Count rows that need updating
            $countToUpdate = DB::table($tableName)
                ->whereNull($tenantColumn)
                ->orWhere($tenantColumn, '!=', $tenantId)
                ->count();

            $totalRows = DB::table($tableName)->count();

            if ($dryRun) {
                $this->line("  [{$tableName}] Would update {$countToUpdate} of {$totalRows} rows");
                $results[$tableName] = $countToUpdate;
            } else {
                // Update all rows
                $updated = DB::table($tableName)
                    ->update([$tenantColumn => $tenantId]);

                $this->info("  [{$tableName}] Updated {$updated} rows");
                $results[$tableName] = $updated;
                $totalUpdated += $updated;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $totalWouldUpdate = array_sum($results);
            $this->info("DRY RUN COMPLETE: Would update {$totalWouldUpdate} rows across " . count($results) . ' tables.');
        } else {
            $this->info("COMPLETE: Updated {$totalUpdated} rows across " . count($results) . ' tables.');
        }

        // Display summary table
        $this->newLine();
        $this->table(
            ['Table', $dryRun ? 'Would Update' : 'Updated'],
            collect($results)->map(fn ($count, $table) => [$table, $count])->values()->toArray()
        );

        return CommandAlias::SUCCESS;
    }
}
