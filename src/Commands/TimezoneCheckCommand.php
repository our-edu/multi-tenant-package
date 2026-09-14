<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Commands;

use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ouredu\MultiTenant\Tenancy\TenantContext;
use Ouredu\MultiTenant\Timezone\TenantTimezone;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * tenant:check-tzdata
 *
 * Fails when the PHP build ships a timezone database that predates Egypt's
 * 2023 return to daylight saving time. Run it in every container entrypoint
 * *before* the process manager starts, so a stale image never serves a
 * Cairo tenant with the wrong offset.
 *
 * With `--tenants` it also validates every stored `tenants.timezone` and
 * `branches.timezone` value against the IANA list.
 */
class TimezoneCheckCommand extends Command
{
    /**
     * Zone / date / expected offset triples that only hold with a current tz database.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    public const EXPECTATIONS = [
        ['Africa/Cairo', '2026-07-01', '+03:00'],
        ['Africa/Cairo', '2026-01-15', '+02:00'],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:check-tzdata
                            {--tenants : Also validate every stored tenant and branch timezone}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the PHP timezone database is current (Egypt DST) and, optionally, that stored tenant/branch timezones are valid IANA names';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('PHP timezone database: ' . timezone_version_get());
        $this->line('date_default_timezone_get(): ' . date_default_timezone_get());
        $this->line('app.timezone: ' . (string) config('app.timezone'));
        $this->newLine();

        $status = $this->checkTzdata();

        if ($this->option('tenants') && ! $this->checkStoredTimezones()) {
            $status = CommandAlias::FAILURE;
        }

        return $status;
    }

    /**
     * Assert the shipped tz database resolves the expected offsets.
     */
    protected function checkTzdata(): int
    {
        $stale = false;

        foreach (self::EXPECTATIONS as [$zone, $date, $expected]) {
            $actual = (new DateTime($date . ' 12:00:00', new DateTimeZone($zone)))->format('P');

            if ($actual === $expected) {
                $this->info("  OK   {$zone} on {$date} is {$actual}");
            } else {
                $stale = true;
                $this->error("  FAIL {$zone} on {$date} is {$actual}, expected {$expected}");
            }
        }

        if ($stale) {
            $this->newLine();
            $this->error(
                'tzdata is stale: Egypt reinstated daylight saving time in 2023. '
                . 'PHP bundles its own timezone table, so upgrade the PHP build or install the '
                . 'timezonedb PECL extension (pecl install timezonedb). System tzdata packages do not fix PHP.'
            );

            return CommandAlias::FAILURE;
        }

        return CommandAlias::SUCCESS;
    }

    /**
     * Validate every stored tenant and branch timezone; returns true when all are valid.
     *
     * This is a cross-tenant administrative scan, so it runs with the tenant
     * context cleared (and restored afterwards) to keep TenantQueryListener quiet.
     */
    protected function checkStoredTimezones(): bool
    {
        $context = app(TenantContext::class);
        $previousTenantId = $context->getTenantId();
        $context->clear();

        try {
            return $this->scanStoredTimezones();
        } finally {
            if ($previousTenantId !== null) {
                $context->setTenantId($previousTenantId);
            }
        }
    }

    /**
     * Uncontextualized scan of tenants.timezone and branches.timezone.
     */
    protected function scanStoredTimezones(): bool
    {
        $column = (string) config('multi-tenant.timezone.column', 'timezone');
        $tenantsTable = (string) config('multi-tenant.timezone.tenants_table', 'tenants');
        $branchesTable = (string) config('multi-tenant.timezone.branches_table', 'branches');
        $tenantColumn = (string) config('multi-tenant.tenant_column', 'tenant_id');

        if (! Schema::hasTable($tenantsTable) || ! Schema::hasColumn($tenantsTable, $column)) {
            $this->error("Table '{$tenantsTable}' has no '{$column}' column. Run the IAM timezone migration first.");

            return false;
        }

        $scanBranches = Schema::hasTable($branchesTable) && Schema::hasColumn($branchesTable, $column);

        if (! $scanBranches) {
            $this->warn("Table '{$branchesTable}' has no '{$column}' column; skipping branch check.");
        }

        $invalid = [];
        $tenants = DB::table($tenantsTable)->orderBy('id')->get(['id', $column]);

        foreach ($tenants as $tenant) {
            if (! TenantTimezone::isValid($tenant->{$column})) {
                $invalid[] = [$tenantsTable, (string) $tenant->id, var_export($tenant->{$column}, true)];
            }

            if (! $scanBranches) {
                continue;
            }

            $branches = DB::table($branchesTable)
                ->where($tenantColumn, $tenant->id)
                ->whereNotNull($column)
                ->get(['uuid', $column]);

            foreach ($branches as $branch) {
                if (! TenantTimezone::isValid($branch->{$column})) {
                    $invalid[] = [$branchesTable, (string) $branch->uuid, var_export($branch->{$column}, true)];
                }
            }
        }

        if ($scanBranches) {
            // Branches without a tenant (the column is nullable)
            $orphans = DB::table($branchesTable)
                ->whereNull($tenantColumn)
                ->whereNotNull($column)
                ->get(['uuid', $column]);

            foreach ($orphans as $branch) {
                if (! TenantTimezone::isValid($branch->{$column})) {
                    $invalid[] = [$branchesTable, (string) $branch->uuid, var_export($branch->{$column}, true)];
                }
            }
        }

        $this->newLine();

        if ($invalid === []) {
            $this->info(sprintf('  OK   %d tenant(s) checked, every stored timezone is a valid IANA name', $tenants->count()));

            return true;
        }

        $this->error(sprintf('  FAIL %d stored timezone(s) are not IANA names (offsets and Etc/GMT aliases are not allowed):', count($invalid)));
        $this->table(['Table', 'Key', 'Value'], $invalid);

        return false;
    }
}
