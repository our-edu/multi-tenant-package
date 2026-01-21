# Laravel Multi-Tenant

[![Packagist Version](https://img.shields.io/packagist/v/our-edu/multi-tenant.svg?style=flat-square)](https://packagist.org/packages/our-edu/multi-tenant)
[![License](https://img.shields.io/packagist/l/our-edu/multi-tenant.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/our-edu/multi-tenant.svg?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/Laravel-9.x%20|%2010.x%20|%2011.x-red.svg?style=flat-square)](composer.json)

A Laravel package for building multi-tenant applications. This package provides tenant context management, automatic query scoping, and model traits for seamless multi-tenancy support.

## Features

- **Tenant Context** - Centralized tenant state management across requests, jobs, and commands
- **Automatic Query Scoping** - All queries automatically filtered by tenant
- **Model Trait** - Simple `HasTenant` trait for tenant-aware models
- **Built-in Resolvers** - Session and Domain resolvers included
- **Flexible Resolution** - Implement your own tenant resolution strategy
- **Middleware Support** - HTTP middleware for tenant resolution
- **Auto-assignment** - Automatically sets tenant ID on model creation/update
- **Zero Configuration** - Works out of the box with sensible defaults
- **Customizable** - Override tenant column names per model
- **Queue Support** - Maintain tenant context in queued jobs
- **Command Support** - Run commands for specific tenants
- **Laravel Octane Compatible** - Uses scoped bindings for request isolation

## Requirements

- PHP 8.1 or higher
- Laravel 9.x, 10.x, or 11.x

## Installation

Install the package via Composer:

```bash
composer require our-edu/multi-tenant
```

The package will auto-register its service provider and automatically publish the configuration file.

## Quick Start

### 1. Configure (Optional)

The package uses `ChainTenantResolver` by default, which tries resolvers in order:
1. `UserSessionTenantResolver` - Gets `tenant_id` from `getSession()` helper
2. `DomainTenantResolver` - Gets `tenant_id` by querying tenant table by domain

Configure the session helper in `config/multi-tenant.php`:
```php
'session' => [
    'helper' => 'getSession',      // Your helper function name
    'tenant_column' => 'tenant_id', // Column on session object
],
```

### 2. Add Trait to Models

**Option A: Use HasTenant Trait Manually**

Add the `HasTenant` trait to models that should be tenant-scoped:

```php
use Illuminate\Database\Eloquent\Model;
use Ouredu\MultiTenant\Traits\HasTenant;

class Project extends Model
{
    use HasTenant;
}
```

**Option B: Use Artisan Command (Recommended)**

Configure your tables and run the command to automatically add the trait:

```php
// config/multi-tenant.php
'tables' => [
    'projects' => \App\Models\Project::class,
    'invoices' => \App\Models\Invoice::class,
    'orders' => \App\Models\Order::class,
],
```

```bash
# Add HasTenant trait to all configured table models
php artisan tenant:add-trait

# Preview changes without modifying files
php artisan tenant:add-trait --dry-run

# Add trait to specific tables only
php artisan tenant:add-trait --table=projects --table=invoices
```

That's it! All queries on configured models will now be automatically scoped to the current tenant.

## Configuration

The configuration file is automatically published to `config/multi-tenant.php`:

```php
return [
    // Your tenant model class (used by DomainTenantResolver)
    'tenant_model' => App\Models\Tenant::class,
    
    // Default tenant column name
    'tenant_column' => 'tenant_id',
    
    // Session configuration (for UserSessionTenantResolver)
    'session' => [
        'helper' => 'getSession',     // Helper function name
        'tenant_column' => 'tenant_id',
    ],
    
    // Domain configuration (for DomainTenantResolver)
    'domain' => [
        'column' => 'domain',
    ],
    
    // Tables mapped to models (for migration, trait command, and query listener)
    'tables' => [
        // 'users' => \App\Models\User::class,
        // 'orders' => \App\Models\Order::class,
    ],
    
    // Query listener (logs queries without tenant_id filter)
    'query_listener' => [
        'enabled' => true,
        'log_channel' => null,  // null = default channel
    ],
];
```

## Database Migration

Add `tenant_id` column to your configured tables:

```bash
# Add tenant_id to all configured tables
php artisan tenant:migrate

# Add tenant_id to specific tables
php artisan tenant:migrate --table=users --table=orders

# Remove tenant_id from tables (rollback)
php artisan tenant:migrate --rollback
```

## Query Listener

The package includes a database query listener that logs errors when queries are executed on tenant tables without a `tenant_id` filter.

### Configuration

```php
'tables' => [
    'users' => \App\Models\User::class,
    'orders' => \App\Models\Order::class,
],

'query_listener' => [
    'enabled' => env('MULTI_TENANT_QUERY_LISTENER_ENABLED', true),
    'log_channel' => env('MULTI_TENANT_QUERY_LISTENER_CHANNEL'),
    'primary_keys' => ['id', 'uuid'],  // Primary key columns to skip
],
```

### Smart Detection

The query listener is smart about detecting safe queries:

- **Primary Key Operations**: UPDATE/DELETE by `id` or `uuid` are considered safe (model was already loaded with tenant scope)
- **Excluded Models**: Models with `$withoutTenantScope = true` are skipped
- **Configurable Primary Keys**: Add custom primary key columns to `primary_keys` config

### Log Output

When a query without tenant filter is detected:
```json
{
    "message": "Query executed without tenant_id filter",
    "context": {
        "table": "orders",
        "sql": "SELECT * FROM orders WHERE status = ?",
        "bindings": ["pending"],
        "tenant_id": 1,
        "file": "/app/Http/Controllers/OrderController.php",
        "line": 45
    }
}
```

## Usage

### Tenant Context

Access the current tenant ID anywhere in your application:

```php
use Ouredu\MultiTenant\Tenancy\TenantContext;

$context = app(TenantContext::class);

// Get current tenant ID
$tenantId = $context->getTenantId();

// Check if tenant exists
if ($context->hasTenant()) {
    // ...
}

// Manually set tenant ID (for testing, jobs, commands)
$context->setTenantId($tenantId);

// Run code in tenant context
$context->runForTenant($tenantId, function () {
    // All queries scoped to this tenant
});
```

### Model Trait

```php
use Ouredu\MultiTenant\Traits\HasTenant;

class Invoice extends Model
{
    use HasTenant;
    
    // Optional: custom tenant column
    public function getTenantColumn(): string
    {
        return 'organization_id';
    }
}
```

The trait provides:
- Automatic global scope for tenant filtering
- Automatic tenant ID assignment on create/update
- `tenant()` relationship method
- `scopeForTenant($query, $tenantId)` scope

### Middleware

Register and use the tenant middleware:

```php
// In bootstrap/app.php or Kernel.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant' => \Ouredu\MultiTenant\Middleware\TenantMiddleware::class,
    ]);
})

// In routes
Route::middleware('tenant')->group(function () {
    Route::resource('projects', ProjectController::class);
});
```

### Queued Jobs

For jobs that need tenant context, set the tenant ID in the job:

```php
class ProcessInvoice implements ShouldQueue
{
    public ?int $tenantId = null;

    public function __construct(public Invoice $invoice)
    {
        $this->tenantId = app(TenantContext::class)->getTenantId();
    }

    public function handle(): void
    {
        // Restore tenant context
        if ($this->tenantId) {
            app(TenantContext::class)->setTenantId($this->tenantId);
        }
        
        // Process invoice...
    }
}
```

### Artisan Commands

Run commands for specific tenants:

```php
class GenerateReports extends Command
{
    protected $signature = 'reports:generate {--tenant= : Tenant ID}';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        
        if ($tenantId) {
            app(TenantContext::class)->setTenantId((int) $tenantId);
        }
        
        // Generate reports...
        
        return self::SUCCESS;
    }
}
```

## API Reference

### TenantContext

| Method | Description |
|--------|-------------|
| `getTenantId(): ?int` | Get the current tenant ID |
| `hasTenant(): bool` | Check if a tenant is set |
| `setTenantId(?int $tenantId): void` | Manually set the tenant ID |
| `clear(): void` | Clear the tenant context |
| `runForTenant(int $tenantId, callable $callback): mixed` | Run callback with specific tenant |

### HasTenant Trait

| Method | Description |
|--------|-------------|
| `tenant(): BelongsTo` | Relationship to tenant model |
| `scopeForTenant($query, int $id): Builder` | Scope to specific tenant |
| `getTenantColumn(): string` | Get tenant column name (override) |

## Testing

```bash
# Run tests
composer test

# Run with coverage
composer test:coverage
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for version history.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Credits

- [OurEdu](https://github.com/ouredu)

