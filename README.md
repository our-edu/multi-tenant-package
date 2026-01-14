# Laravel Multi-Tenant

[![Packagist Version](https://img.shields.io/packagist/v/ouredu/multi-tenant.svg?style=flat-square)](https://packagist.org/packages/ouredu/multi-tenant)
[![License](https://img.shields.io/packagist/l/ouredu/multi-tenant.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/ouredu/multi-tenant.svg?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/Laravel-9.x%20|%2010.x%20|%2011.x-red.svg?style=flat-square)](composer.json)

A Laravel package for building multi-tenant applications. This package provides tenant context management, automatic query scoping, and model traits for seamless multi-tenancy support.

## Features

- **Tenant Context** - Centralized tenant state management across requests, jobs, and commands
- **Automatic Query Scoping** - All queries automatically filtered by tenant
- **Model Trait** - Simple `HasTenant` trait for tenant-aware models
- **Flexible Resolution** - Implement your own tenant resolution strategy
- **Middleware Support** - HTTP middleware for tenant resolution
- **Auto-assignment** - Automatically sets tenant ID on model creation/update
- **Zero Configuration** - Works out of the box with sensible defaults
- **Customizable** - Override tenant column names per model
- **Queue Support** - Maintain tenant context in queued jobs
- **Command Support** - Run commands for specific tenants

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

### 1. Implement a Tenant Resolver

Create a resolver that determines the current tenant:

```php
use Ouredu\MultiTenant\Contracts\TenantResolver;

class AppTenantResolver implements TenantResolver
{
    public function resolveTenant(): ?Model
    {
        // Resolve from authenticated user
        return auth()->user()?->tenant;
        
        // Or from session
        // return Tenant::find(session('tenant_id'));
        
        // Or from subdomain
        // $subdomain = explode('.', request()->getHost())[0];
        // return Tenant::where('subdomain', $subdomain)->first();
    }
}
```

### 2. Register the Resolver

In your `AppServiceProvider`:

```php
use Ouredu\MultiTenant\Contracts\TenantResolver;

public function register(): void
{
    $this->app->bind(TenantResolver::class, AppTenantResolver::class);
}
```

### 3. Add Trait to Models

Add the `HasTenant` trait to models that should be tenant-scoped:

```php
use Illuminate\Database\Eloquent\Model;
use Ouredu\MultiTenant\Traits\HasTenant;

class Project extends Model
{
    use HasTenant;
}
```

That's it! All queries on `Project` will now be automatically scoped to the current tenant.

## Configuration

The configuration file is automatically published to `config/multi-tenant.php`:

```php
return [
    // Your tenant model class
    'tenant_model' => App\Models\Tenant::class,
    
    // Default tenant column name
    'tenant_column' => 'tenant_id',
];
```

## Usage

### Tenant Context

Access the current tenant anywhere in your application:

```php
use Ouredu\MultiTenant\Tenancy\TenantContext;

$context = app(TenantContext::class);

// Get current tenant
$tenant = $context->getTenant();

// Get tenant ID
$tenantId = $context->getTenantId();

// Check if tenant exists
if ($context->hasTenant()) {
    // ...
}

// Manually set tenant (for testing, jobs, commands)
$context->setTenant($tenant);
$context->setTenantById($tenantId);

// Run code in tenant context
$context->runWithTenant($tenant, function ($tenant) {
    // All queries scoped to $tenant
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
    public ?string $tenantId = null;

    public function __construct(public Invoice $invoice)
    {
        $this->tenantId = app(TenantContext::class)->getTenantId();
    }

    public function handle(): void
    {
        // Restore tenant context
        app(TenantContext::class)->setTenantById($this->tenantId);
        
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
            app(TenantContext::class)->setTenantById($tenantId);
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
| `getTenant(): ?Model` | Get the current tenant model |
| `getTenantId(): ?string` | Get the current tenant ID |
| `hasTenant(): bool` | Check if a tenant is set |
| `setTenant(?Model $tenant): void` | Manually set the tenant |
| `setTenantById(string $id): ?Model` | Set tenant by ID |
| `clear(): void` | Clear the tenant context |
| `runWithTenant(Model $tenant, callable $callback): mixed` | Run callback with tenant |
| `runWithTenantId(string $id, callable $callback): mixed` | Run callback with tenant ID |

### HasTenant Trait

| Method | Description |
|--------|-------------|
| `tenant(): BelongsTo` | Relationship to tenant model |
| `scopeForTenant($query, string $id): Builder` | Scope to specific tenant |
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

