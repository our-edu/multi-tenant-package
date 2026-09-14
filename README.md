# Laravel Multi-Tenant

[![Packagist Version](https://img.shields.io/packagist/v/our-edu/multi-tenant.svg?style=flat-square)](https://packagist.org/packages/our-edu/multi-tenant)
[![License](https://img.shields.io/packagist/l/our-edu/multi-tenant.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/our-edu/multi-tenant.svg?style=flat-square)](composer.json)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20|%2011.x%20|%2012.x-red.svg?style=flat-square)](composer.json)

A Laravel package for building multi-tenant applications. This package provides tenant context management, automatic query scoping, and model traits for seamless multi-tenancy support.

## Features

- **Tenant Context** - Centralized tenant state management across requests, jobs, and commands
- **Automatic Query Scoping** - All queries automatically filtered by tenant
- **Model Trait** - Simple `HasTenant` trait for tenant-aware models
- **Built-in Resolvers** - Session and Header resolvers included
- **Flexible Resolution** - Implement your own tenant resolution strategy
- **Middleware Support** - HTTP middleware for tenant resolution with excluded routes
- **Exception Handling** - Throws exception when tenant cannot be resolved (translatable messages)
- **Auto-assignment** - Automatically sets tenant ID on model creation/update
- **Zero Configuration** - Works out of the box with sensible defaults
- **Customizable** - Override tenant column names per model
- **Queue Support** - Maintain tenant context in queued jobs
- **Command Support** - Run commands for specific tenants
- **Laravel Octane Compatible** - Uses scoped bindings for request isolation
- **Validation Awareness** - `exists` and `unique` rules can auto-scope by tenant
- **Tenant Timezone** - Per-tenant/branch IANA zone from the JWT claim or the database, an instant cast, ISO-8601 serialization and a tzdata guard

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x

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
2. `HeaderTenantResolver` - Gets `tenant_id` from `X-Tenant-ID` header

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
    
    // Header configuration (for HeaderTenantResolver)
    'header' => [
        'name' => 'X-Tenant-ID',      // Header name containing tenant ID
        'routes' => [                  // Routes where header resolution is allowed
            // 'api.external.*',
            // 'api/v1/external/*',
        ],
    ],
    
    // Production app prefix (prepended to excluded routes)
    'production_app_prefix' => env('PRODUCTION_APP_PREFIX'),
    
    // Excluded routes (bypass tenant resolution in middleware)
    // Supports path patterns with wildcards (asterisk matches any segment)
    'excluded_routes' => [
        // API routes: 'api/*/*/health' matches api/v1/ar/health, api/v2/en/health
        // Web routes: 'health' matches /health
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

    // Validation scope for database rules (exists / unique)
    'validation' => [
        'apply_tenant_scope' => true,
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

### Validation Rules (`exists` / `unique`)

Database-backed validation rules are tenant-aware by default when tenant context is available.

```php
use Illuminate\Support\Facades\Validator;

app(\Ouredu\MultiTenant\Tenancy\TenantContext::class)->setTenantId(10);

$validator = Validator::make($data, [
    'email' => ['required', 'email', 'unique:users,email'],
    'user_uuid' => ['required', 'exists:users,uuid'],
]);
```

With this, checks are automatically scoped to the current tenant by adding
`tenant_id = current_tenant_id` to the validation query.

Scoped table names are taken from `multi-tenant.tables`, so you only configure
the table list once.

You can configure this behavior in `config/multi-tenant.php`:

```php
'validation' => [
    'apply_tenant_scope' => true,
],
```

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

#### Excluded Routes

Configure routes that should bypass tenant resolution using path patterns:

```php
// config/multi-tenant.php
'excluded_routes' => [
    // API routes (with version/lang prefix) - use wildcards
    'api/*/*/health',           // matches api/v1/ar/health, api/v2/en/health
    'api/*/*/webhook/*',        // matches api/v1/ar/webhook/ottu, api/v2/en/webhook/stripe
    'api/*/*/public/*',         // matches api/v1/ar/public/docs
    
    // Web routes (without prefix) - exact match
    'health',                   // matches /health
    'login',                    // matches /login
    'register',                 // matches /register
],
```

**Production App Prefix:**

If your application runs behind a reverse proxy or has a production-specific URL prefix, you can configure it to be automatically prepended to all excluded routes:

```php
// config/multi-tenant.php
'production_app_prefix' => env('PRODUCTION_APP_PREFIX'),

'excluded_routes' => [
    'health',
    'login',
],
```

```env
# .env
PRODUCTION_APP_PREFIX=api/v1
```

With this configuration, the excluded routes will become `api/v1/health` and `api/v1/login`.

**Pattern Syntax:**
- Use `*` (asterisk) as wildcard to match any single path segment
- `api/*/*/users` matches `api/v1/ar/users`, `api/v2/en/users`, etc.
- `webhook/*` matches `webhook/ottu`, `webhook/stripe`, etc.
- Leading slashes are automatically trimmed

#### Exception Handling

When no resolver can determine the tenant ID, a `TenantNotResolvedException` is thrown. This ensures all non-excluded routes have a valid tenant context.

```php
use Ouredu\MultiTenant\Exceptions\TenantNotResolvedException;

// Handle in your exception handler
public function render($request, Throwable $e)
{
    if ($e instanceof TenantNotResolvedException) {
        return response()->json(['error' => 'Tenant not found'], 404);
    }
    
    return parent::render($request, $e);
}
```

### Header Tenant Resolver

For API routes where the tenant ID is passed as a header (e.g., external integrations, webhooks):

```php
// config/multi-tenant.php
'header' => [
    'name' => 'X-Tenant-ID',      // Header name
    'routes' => [
        'api.external.*',          // Route name pattern
        'api/v1/webhook/*',        // URI pattern
    ],
],
```

Then send requests with the header:
```bash
curl -H "X-Tenant-ID: 123" https://api.example.com/api/v1/webhook/process
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

### Event/Message Listeners

For listeners that receive messages with tenant context, use the `SetsTenantFromPayload` trait:

```php
use Ouredu\MultiTenant\Traits\SetsTenantFromPayload;

class PaymentCreatedListener
{
    use SetsTenantFromPayload;

    public function handle(PaymentCreatedEvent $event): void
    {
        // Set tenant from message payload
        // Throws TenantNotFoundException if tenant_id not found and fallback disabled
        $this->setTenantFromPayload($event->payload);

        // Now all queries will be tenant-scoped
        $order = Order::find($event->orderId);
    }
}
```

**Automatically Add Trait to Listeners**

Use the artisan command to add `SetsTenantFromPayload` trait to all listeners in a config file:

```bash
# From a config file (by name, e.g., sqs_events.php in config directory)
php artisan tenant:add-listener-trait --config=sqs_events

# Preview changes without modifying files
php artisan tenant:add-listener-trait --config=sqs_events --dry-run

# From multi-tenant.php config
php artisan tenant:add-listener-trait

# Add trait to specific listener class
php artisan tenant:add-listener-trait --listener="App\Listeners\PaymentCreatedListener"
```

The command supports various config file formats:
- SQS events style: `'event.type' => ListenerClass::class`
- EventServiceProvider style: `['Event' => [ListenerClass::class]]`
- Simple array: `[ListenerClass::class, ...]`

Configure the listener fallback behavior in `config/multi-tenant.php`:

```php
'listener' => [
    // Fallback to database when tenant_id not in payload (queries where is_active = true)
    'fallback_to_database' => env('MULTI_TENANT_LISTENER_FALLBACK_DB', false),
],
```

The trait works with both array and object payloads:
- First checks if `tenant_id` exists in the payload
- If not found and `fallback_to_database` is true, queries tenant table where `is_active = true`
- If fallback is disabled or no active tenant found, throws `TenantNotFoundException`

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

### Tenant Timezone

Every tenant (and optionally every branch) has an IANA timezone (`Africa/Cairo`, never an offset).
IAM mints it into the JWT as a `timezone` claim and stores it in the shared `tenants.timezone` /
`branches.timezone` columns. This package resolves it anywhere:

```php
use Ouredu\MultiTenant\Timezone\TenantTimezone;

// HTTP request: the session's `timezone` claim, else the tenant/branch row, else app.timezone
$zone = TenantTimezone::current();

// Jobs, listeners, cron: resolve explicitly from ids (memoized per request/job)
$zone = TenantTimezone::for($tenantId, $branchUuid);   // '*' or null branch → tenant zone

// Render text (push/SMS bodies, PDFs, exports) in the tenant zone
$text = $quiz->end_at->inTenantTz()->format('Y-m-d H:i');           // current tenant
$text = $quiz->end_at->inTenantTz('Africa/Cairo')->format('H:i');   // explicit (jobs/cron)

// Parse an incoming instant: an explicit offset is honoured, naive input is read in the tenant zone
$startsAt = TenantTimezone::parse($request->input('starts_at'));
```

`current()` reads the session helper (`multi-tenant.session.helper`) for the `timezone` attribute,
so each service must copy the claim onto its session object:

```php
// AppServiceProvider — where the UserSession is hydrated from /token/claims
$userSession->timezone = $tokenClaims->timezone;
```

In console (queue workers, cron) the session is skipped, exactly like tenant resolution: `current()`
then uses the tenant set on `TenantContext` (jobs that call `setTenantId()` / `SetsTenantFromPayload`).
Cron code that iterates tenants must call `for()` per tenant.

**Instant columns** (moments in time, stored as naive timestamps in `app.timezone`) use the
`UtcDateTime` cast. Reading is identical to Laravel's `datetime` cast; writing converts to the storage
zone first, so a Carbon assigned in another zone or a client string with an offset keeps its instant
(Laravel's `datetime` cast silently drops the zone):

```php
use Ouredu\MultiTenant\Casts\UtcDateTime;

protected $casts = [
    'starts_at' => UtcDateTime::class,   // instant
    'birthdate' => 'date:Y-m-d',         // wall-clock date: never converted, never ISO-serialized
];
```

**Wall-clock values** (timetable `from`/`to`, `scheduled_time`, `HH:mm`, birthdates, academic-year
bounds) are never converted: leave them as plain strings or `date:Y-m-d` / `datetime:H:i` casts.

**Serialization**: add `SerializesDatesAsIso` to your base model so every date attribute goes out as
ISO-8601 with an offset (`2026-09-13T07:00:00+00:00`), which is correct whatever the service's
`app.timezone` is. Because Laravel routes plain `date` casts through the same serializer, date-only
columns must use `date:Y-m-d` (see above).

```php
use Ouredu\MultiTenant\Traits\SerializesDatesAsIso;

abstract class BaseModel extends Model
{
    use SerializesDatesAsIso;
}
```

**Container guard**: PHP bundles its own timezone table, and builds older than 2023 do not know that
Egypt reinstated daylight saving time. Run the check in every entrypoint *before* the process manager:

```bash
php artisan tenant:check-tzdata            # fails the container when tzdata is stale
php artisan tenant:check-tzdata --tenants  # also validates every stored tenant/branch zone
```

Config (`multi-tenant.timezone.*`): `default` (fallback zone, null → `app.timezone`), `column`,
`tenants_table`, `branches_table`, `session_attribute`, `session_branch_attribute`.

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

### TenantTimezone

| Method | Description |
|--------|-------------|
| `current(): string` | Zone of the current request's tenant/branch (session claim → tenant row → default) |
| `for(?int $tenantId, ?string $branchUuid = null): string` | Zone of a tenant/branch from the database (memoized per request/job) |
| `parse(mixed $value, ?string $timezone = null): ?Carbon` | Parse an incoming instant; naive input is read in the tenant zone; result in the storage zone |
| `set(?string $timezone): void` | Override the current zone for the rest of the request/job |
| `runIn(string $timezone, callable $callback): mixed` | Run callback with a specific current zone |
| `storage(): string` | Zone naive database values are stored in (`app.timezone`) |
| `isValid(mixed $timezone): bool` | Whether the value is an IANA zone name (offsets and `Etc/GMT±n` are rejected) |
| `ALL_BRANCHES` | `'*'` — branch claim meaning "all branches" (use the tenant zone) |

### Carbon Macro

| Method | Description |
|--------|-------------|
| `$date->inTenantTz(?string $timezone = null): static` | Copy of the date in the current (or given) tenant zone |

### UtcDateTime Cast / SerializesDatesAsIso Trait

| Item | Description |
|--------|-------------|
| `UtcDateTime::class` | Cast for instant columns: reads like `datetime`, writes via `TenantTimezone::parse()` |
| `SerializesDatesAsIso` | Model trait: `serializeDate()` → ISO-8601 with offset |

## Translations

The package supports translatable exception messages. Language files are **automatically published** when the package is installed.

**Supported languages:** English (en), Arabic (ar)

To manually re-publish or update the language files:

```bash
php artisan vendor:publish --tag=multi-tenant-lang --force
```

Published files location: `lang/vendor/multi-tenant/`

```php
// lang/vendor/multi-tenant/en/exceptions.php
return [
    'tenant_not_resolved' => 'Unable to resolve tenant. No resolver returned a valid tenant ID.',
];

// lang/vendor/multi-tenant/ar/exceptions.php
return [
    'tenant_not_resolved' => 'غير قادر على تحديد المستأجر. لم يُرجع أي محلل معرف مستأجر صالح.',
];
```

### Adding More Languages

Create additional language files in `lang/vendor/multi-tenant/{locale}/exceptions.php`:

```php
// lang/vendor/multi-tenant/fr/exceptions.php
return [
    'tenant_not_resolved' => 'Impossible de résoudre le locataire. Aucun résolveur n\'a retourné un ID de locataire valide.',
];
```

### SetsTenantFromPayload Trait

| Method | Description |
|--------|-------------|
| `setTenantFromPayload(array\|object $payload): void` | Set tenant context from payload, throws TenantNotFoundException if not found |

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

