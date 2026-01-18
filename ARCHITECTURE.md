# Multi-Tenant Architecture Documentation

> Copyright 2026 OurEdu - Multi-Tenant Infrastructure for Laravel Services

This document describes the architecture and design decisions behind the multi-tenant package for OurEdu Laravel services.

---

## Table of Contents

- [Overview](#overview)
- [Architecture Design](#architecture-design)
- [Core Components](#core-components)
- [Data Flow](#data-flow)
- [Package Structure](#package-structure)
- [Integration Guide](#integration-guide)
- [Best Practices](#best-practices)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

---

## Overview

### What is Multi-Tenancy?

Multi-tenancy is an architecture where a single instance of the application serves multiple tenants (customers/organizations). Each tenant's data is isolated and invisible to other tenants.

### Architecture Pattern

This package implements a **Shared Database, Shared Schema** pattern with **Row-Level Security**:

- All tenants share the same database and schema
- Each row has a `tenant_id` column
- Queries are automatically filtered by `tenant_id`

### Key Benefits

| Benefit | Description |
|---------|-------------|
| **Automatic Data Isolation** | All queries are automatically filtered by tenant |
| **Security** | Prevents cross-tenant data access at the database query level |
| **Minimal Code Changes** | Existing code works with minimal modifications |
| **Flexible** | Models can opt-out of tenant scoping when needed |
| **Service Agnostic** | Each service implements its own tenant resolution strategy |
| **Octane Compatible** | Uses scoped bindings for proper request isolation |

---

## Architecture Design

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         HTTP Request                            │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      TenantMiddleware                           │
│              (Initializes tenant context early)                 │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                       TenantContext                             │
│                  (Scoped per request)                           │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │         ChainTenantResolver (Default)                   │    │
│  │   1. UserSessionTenantResolver → getSession()->tenant_id│    │
│  │   2. DomainTenantResolver → query by domain             │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        TenantScope                              │
│            (Global scope on Eloquent models)                    │
│                                                                 │
│     SELECT * FROM users WHERE tenant_id = 1 AND ...             │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                         Database                                │
│              (Shared database, tenant_id column)                │
└─────────────────────────────────────────────────────────────────┘
```

### Resolution Strategies

The package includes built-in resolvers and supports custom implementations:

| Strategy | Resolver | Example |
|----------|----------|---------|
| **Session** | `UserSessionTenantResolver` | `getSession()->tenant_id` |
| **Domain** | `DomainTenantResolver` | Query tenant by `domain` column |
| **Chain** | `ChainTenantResolver` | Tries session first, then domain |
| **Custom** | Your implementation | Header, CLI args, message payload |

---

## Core Components

### 1. TenantContext

The central service managing tenant ID state throughout the request lifecycle.

**Location:** `src/Tenancy/TenantContext.php`

**Key Methods:**

```php
use Ouredu\MultiTenant\Tenancy\TenantContext;

$context = app(TenantContext::class);

// Get current tenant ID
$tenantId = $context->getTenantId();  // Returns int|null

// Check if tenant is set
if ($context->hasTenant()) {
    // Tenant is available
}

// Manually set tenant ID (testing/jobs/commands)
$context->setTenantId(1);

// Run code in tenant context
$context->runForTenant(1, function () {
    // Code runs with this tenant
});

// Clear tenant context
$context->clear();
```

**Characteristics:**
- Scoped binding (one instance per request, safe for Laravel Octane)
- Lazy-loaded (resolved only when accessed)
- Thread-safe for queue workers
- Automatically cleared between requests in Octane

---

### 2. TenantScope

Global Eloquent scope that automatically filters queries by tenant.

**Location:** `src/Tenancy/TenantScope.php`

**Automatic Filtering:**

```php
// Automatically adds: WHERE tenant_id = 1
$users = User::all();
$payments = Payment::where('status', 'paid')->get();
```

**Bypassing the Scope:**

```php
// Query without tenant filtering (admin operations)
$allUsers = User::withoutTenantScope()->get();

// Query specific tenant
$users = User::forTenant(5)->get();
```

**Model Opt-Out:**

```php
class GlobalSetting extends Model
{
    use HasTenant;
    
    // This model won't be filtered by tenant
    public bool $withoutTenantScope = true;
}
```

---

### 3. TenantResolver (Contract)

Interface that defines how tenant IDs are resolved.

**Location:** `src/Contracts/TenantResolver.php`

```php
use Ouredu\MultiTenant\Contracts\TenantResolver;

class CustomTenantResolver implements TenantResolver
{
    public function resolveTenantId(): ?int
    {
        $session = getSession(); // Your session helper
        
        if (!$session || !$session->tenant_id) {
            return null;
        }
        
        return $session->tenant_id;
    }
}
```

#### Built-in Resolvers

**UserSessionTenantResolver** - Gets tenant_id from a configurable helper function:
```php
// Configure in config/multi-tenant.php
'session' => [
    'helper' => 'getSession',  // Your helper function name
    'tenant_column' => 'tenant_id',
],

// Reads tenant_id from session object returned by the helper
$tenantId = getSession()?->tenant_id;
```

**DomainTenantResolver** - Gets tenant_id by querying tenant table by domain:
```php
// Queries: SELECT id FROM tenants WHERE domain = 'school1.ouredu.com'
$tenantId = Tenant::where('domain', $host)->value('id');
```

**ChainTenantResolver** - Chains multiple resolvers (default):
```php
// Tries UserSessionTenantResolver first, then DomainTenantResolver
$resolver = new ChainTenantResolver([
    new UserSessionTenantResolver(),
    new DomainTenantResolver(),
]);
```

#### Creating a Custom TenantResolver

You can create your own resolver to implement custom tenant resolution logic. Here are common use cases:

**Example 1: Header-Based Resolver**

```php
// app/Resolvers/HeaderTenantResolver.php
namespace App\Resolvers;

use Illuminate\Http\Request;
use Ouredu\MultiTenant\Contracts\TenantResolver;

class HeaderTenantResolver implements TenantResolver
{
    public function resolveTenantId(): ?int
    {
        $request = app(Request::class);
        $tenantId = $request->header('X-Tenant-ID');
        
        return $tenantId ? (int) $tenantId : null;
    }
}
```

**Example 2: Authenticated User Resolver**

```php
// app/Resolvers/AuthUserTenantResolver.php
namespace App\Resolvers;

use Ouredu\MultiTenant\Contracts\TenantResolver;

class AuthUserTenantResolver implements TenantResolver
{
    public function resolveTenantId(): ?int
    {
        $user = auth()->user();
        
        return $user?->tenant_id;
    }
}
```

**Example 3: JWT Token Resolver**

```php
// app/Resolvers/JwtTenantResolver.php
namespace App\Resolvers;

use Ouredu\MultiTenant\Contracts\TenantResolver;

class JwtTenantResolver implements TenantResolver
{
    public function resolveTenantId(): ?int
    {
        try {
            $token = request()->bearerToken();
            if (!$token) {
                return null;
            }
            
            $payload = json_decode(base64_decode(explode('.', $token)[1]), true);
            
            return isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

#### Registering a Custom TenantResolver

You have two options for registering your custom resolver:

**Option 1: Replace the Default Resolver**

Replace the default `ChainTenantResolver` with your custom resolver:

```php
// app/Providers/AppServiceProvider.php
use Ouredu\MultiTenant\Contracts\TenantResolver;
use App\Resolvers\HeaderTenantResolver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Replace the default resolver with your custom one
        $this->app->bind(TenantResolver::class, HeaderTenantResolver::class);
    }
}
```

**Option 2: Add to ChainTenantResolver (Recommended)**

Add your custom resolver to the chain while keeping the built-in resolvers:

```php
// app/Providers/AppServiceProvider.php
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Ouredu\MultiTenant\Resolvers\UserSessionTenantResolver;
use Ouredu\MultiTenant\Resolvers\DomainTenantResolver;
use App\Resolvers\HeaderTenantResolver;
use App\Resolvers\JwtTenantResolver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Create a custom chain with your resolvers
        $this->app->bind(TenantResolver::class, function ($app) {
            return new ChainTenantResolver([
                // Try JWT token first (most secure)
                new JwtTenantResolver(),
                
                // Then try header
                new HeaderTenantResolver(),
                
                // Then try session (built-in)
                new UserSessionTenantResolver(),
                
                // Finally try domain (built-in)
                new DomainTenantResolver(),
            ]);
        });
    }
}
```

**Option 3: Extend ChainTenantResolver**

Create a custom chain resolver class:

```php
// app/Resolvers/CustomChainTenantResolver.php
namespace App\Resolvers;

use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Ouredu\MultiTenant\Resolvers\UserSessionTenantResolver;
use Ouredu\MultiTenant\Resolvers\DomainTenantResolver;

class CustomChainTenantResolver extends ChainTenantResolver
{
    protected function getDefaultResolvers(): array
    {
        return [
            new HeaderTenantResolver(),        // Your custom resolver first
            new UserSessionTenantResolver(),   // Then built-in resolvers
            new DomainTenantResolver(),
        ];
    }
}
```

Then register it:

```php
// app/Providers/AppServiceProvider.php
use Ouredu\MultiTenant\Contracts\TenantResolver;
use App\Resolvers\CustomChainTenantResolver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantResolver::class, CustomChainTenantResolver::class);
    }
}
```

**Important Notes:**

- Resolvers are tried in order until one returns a non-null tenant ID
- Once a resolver returns a tenant ID, the chain stops and that ID is used
- If all resolvers return `null`, the tenant context will be empty
- The order matters - put the most reliable/fastest resolvers first
- Console commands skip resolution unless running unit tests

---

### 4. HasTenant Trait

Model trait providing tenant relationship and automatic tenant assignment.

**Location:** `src/Traits/HasTenant.php`

**Usage:**

```php
use Ouredu\MultiTenant\Traits\HasTenant;

class Payment extends Model
{
    use HasTenant;
    
    // TenantScope is automatically registered by the trait
    // No manual setup required
}
```

**Features:**
- `tenant()` - BelongsTo relationship
- `scopeForTenant($query, $tenantId)` - Query scope
- Automatic `tenant_id` assignment on create/update
- Automatic `TenantScope` registration (no manual setup needed)
- Custom tenant column support

**Custom Tenant Column:**

```php
class Payment extends Model
{
    use HasTenant;
    
    // Method approach
    public function getTenantColumn(): string
    {
        return 'organization_id';
    }
    
    // Or property approach
    public string $tenantColumn = 'organization_id';
}
```

---

### 5. TenantMiddleware

HTTP middleware that initializes tenant context early in the request lifecycle.

**Location:** `src/Middleware/TenantMiddleware.php`

**How it connects to ChainTenantResolver:**

The middleware doesn't directly use `ChainTenantResolver`. Instead, it follows this flow:

1. Middleware calls `app(TenantContext::class)`
2. Service container resolves `TenantContext`
3. `TenantContext` constructor receives `TenantResolver` (injected via DI)
4. The `TenantResolver` is whatever you bound in `AppServiceProvider` (default: `ChainTenantResolver`)
5. When `$context->getTenantId()` is called, it triggers `$resolver->resolveTenantId()`
6. If you bound `ChainTenantResolver`, it tries each resolver in the chain

**Registration:**

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'tenant' => \Ouredu\MultiTenant\Middleware\TenantMiddleware::class,
];

// routes/api.php
Route::middleware(['auth', 'tenant'])->group(function () {
    // Tenant-scoped routes
});
```

**Implementation:**

```php
public function handle(Request $request, Closure $next): mixed
{
    /** @var TenantContext $context */
    $context = app(TenantContext::class);
    
    // This triggers lazy resolution via the bound TenantResolver
    // If ChainTenantResolver is bound, it will try each resolver in order
    $context->getTenantId();
    
    return $next($request);
}
```

---

## Data Flow

### Web Request Flow

```
1. Request arrives
2. TenantMiddleware triggers TenantContext
3. TenantContext calls TenantResolver
4. TenantResolver resolves tenant_id (from session/domain)
5. TenantContext caches the tenant_id
6. TenantScope uses TenantContext for all queries
7. Models automatically filter by tenant_id
```

**Detailed Flow from Middleware to ChainTenantResolver:**

```
HTTP Request
    │
    ▼
TenantMiddleware::handle()
    │
    ├─→ app(TenantContext::class)  [Service Container resolves TenantContext]
    │       │
    │       └─→ TenantServiceProvider registers TenantContext
    │               │
    │               └─→ new TenantContext($app->make(TenantResolver::class))
    │                       │
    │                       └─→ Service Container resolves TenantResolver
    │                               │
    │                               ├─→ If bound in AppServiceProvider:
    │                               │       └─→ Uses your custom binding
    │                               │
    │                               └─→ If not bound (default):
    │                                       └─→ Laravel tries to instantiate
    │                                           (You should bind ChainTenantResolver)
    │
    ├─→ $context->getTenantId()  [Triggers lazy resolution]
    │       │
    │       └─→ $this->resolver->resolveTenantId()
    │               │
    │               └─→ ChainTenantResolver::resolveTenantId()
    │                       │
    │                       ├─→ Try UserSessionTenantResolver::resolveTenantId()
    │                       │       └─→ getSession()?->tenant_id
    │                       │
    │                       └─→ If null, try DomainTenantResolver::resolveTenantId()
    │                               └─→ Tenant::where('domain', $host)->value('id')
    │
    └─→ Tenant ID cached in TenantContext for request lifetime
```

**Service Container Binding Flow:**

The connection happens through Laravel's service container:

1. **TenantServiceProvider** (package) registers `TenantContext`:
   ```php
   $this->app->scoped(TenantContext::class, function (Application $app): TenantContext {
       return new TenantContext($app->make(TenantResolver::class));
   });
   ```

2. **TenantServiceProvider** (package) binds `ChainTenantResolver` as default:
   ```php
   // Package automatically binds this (uses bind() for Octane compatibility):
   $this->app->bind(TenantResolver::class, ChainTenantResolver::class);
   ```

3. **AppServiceProvider** (your app) can optionally override `TenantResolver`:
   ```php
   // Option 1: Use default ChainTenantResolver (no binding needed!)
   // The package provides this by default
   
   // Option 2: Override with custom chain
   use Ouredu\MultiTenant\Contracts\TenantResolver;
   use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
   use Ouredu\MultiTenant\Resolvers\UserSessionTenantResolver;
   use Ouredu\MultiTenant\Resolvers\DomainTenantResolver;
   
   $this->app->bind(TenantResolver::class, function ($app) {
       return new ChainTenantResolver([
           new YourCustomResolver(),
           new UserSessionTenantResolver(),
           new DomainTenantResolver(),
       ]);
   });
   
   // Option 3: Replace with completely custom resolver
   $this->app->bind(TenantResolver::class, YourCustomResolver::class);
   ```

4. **When TenantMiddleware runs:**
   - Calls `app(TenantContext::class)`
   - Container resolves `TenantContext`, which needs `TenantResolver`
   - Container resolves `TenantResolver` (default: `ChainTenantResolver` from package, or your override)
   - `TenantContext` calls `$resolver->resolveTenantId()`
   - `ChainTenantResolver` tries each resolver in order

**Important:** The package provides `ChainTenantResolver` as the default binding. You only need to bind `TenantResolver::class` in your `AppServiceProvider` if you want to customize or replace it.

### Queue Job Flow

```
1. Job is dispatched with tenant_id stored in job property
2. Job is processed by queue worker
3. Job calls setTenantId() to restore tenant context
4. Job code runs with correct tenant
5. TenantScope filters all queries
```

### Console Command Flow

```
1. Command receives --tenant option
2. Command calls setTenantId() with provided ID
3. Command code runs with tenant context
4. TenantScope filters all queries
```

---

## Package Structure

```
multi-tenant-package/
├── config/
│   └── multi-tenant.php          # Package configuration
├── src/
│   ├── Commands/
│   │   └── TenantMigrateCommand.php  # Add tenant_id to tables
│   ├── Contracts/
│   │   └── TenantResolver.php    # Interface for tenant resolution
│   ├── Listeners/
│   │   └── TenantQueryListener.php   # Logs queries without tenant_id
│   ├── Middleware/
│   │   └── TenantMiddleware.php  # HTTP middleware
│   ├── Providers/
│   │   └── TenantServiceProvider.php
│   ├── Resolvers/
│   │   ├── ChainTenantResolver.php
│   │   ├── DomainTenantResolver.php
│   │   └── UserSessionTenantResolver.php
│   ├── Tenancy/
│   │   ├── TenantContext.php     # Central tenant service
│   │   └── TenantScope.php       # Global query scope
│   └── Traits/
│       └── HasTenant.php         # Model trait
├── tests/
│   └── ...
├── composer.json
├── README.md
├── CONTRIBUTING.md
└── ARCHITECTURE.md               # This file
```

---

## Integration Guide

### Step 1: Install Package

```bash
composer require our-edu/multi-tenant
```

The package will auto-register its service provider and automatically publish the configuration file.

### Step 2: Implement TenantResolver (Optional)

The package automatically binds `ChainTenantResolver` as the default `TenantResolver`. This includes `UserSessionTenantResolver` and `DomainTenantResolver` by default.

**Option A: Use Built-in Resolvers (Default - Recommended)**

No configuration needed! The package automatically provides `ChainTenantResolver` with `UserSessionTenantResolver` and `DomainTenantResolver`. Just install the package and it works out of the box.

**Option B: Add Custom Resolver to Chain**

If you need to add a custom resolver (e.g., header-based, JWT token) while keeping the built-in resolvers:

```php
// app/Providers/AppServiceProvider.php
use Ouredu\MultiTenant\Contracts\TenantResolver;
use Ouredu\MultiTenant\Resolvers\ChainTenantResolver;
use Ouredu\MultiTenant\Resolvers\UserSessionTenantResolver;
use Ouredu\MultiTenant\Resolvers\DomainTenantResolver;
use App\Resolvers\HeaderTenantResolver; // Your custom resolver

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantResolver::class, function ($app) {
            return new ChainTenantResolver([
                new HeaderTenantResolver(),        // Your custom resolver first
                new UserSessionTenantResolver(),   // Then built-in resolvers
                new DomainTenantResolver(),
            ]);
        });
    }
}
```

**Option C: Replace with Custom Resolver**

If you want to completely replace the default resolver:

```php
// app/Providers/AppServiceProvider.php
use Ouredu\MultiTenant\Contracts\TenantResolver;
use App\Resolvers\CustomTenantResolver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantResolver::class, CustomTenantResolver::class);
    }
}
```

**See [Creating a Custom TenantResolver](#3-tenantresolver-contract) section above for detailed examples and implementation patterns.**

### Step 3: Add Middleware

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'tenant' => \Ouredu\MultiTenant\Middleware\TenantMiddleware::class,
];
```

### Step 4: Update Models

```php
use Ouredu\MultiTenant\Traits\HasTenant;

class YourModel extends Model
{
    use HasTenant;
    
    // TenantScope is automatically added by HasTenant trait
    // No need to manually add it in booted()
}
```

**Note:** The `HasTenant` trait automatically registers the `TenantScope` when the model boots. You don't need to manually add it unless you want to customize the behavior.

### Step 5: Add Database Column

```php
// Migration
Schema::table('your_table', function (Blueprint $table) {
    $table->unsignedBigInteger('tenant_id')->nullable()->index();
});
```

---

## Best Practices

### 1. Tenant Resolution

| ✅ Do | ❌ Don't |
|-------|----------|
| Use `TenantContext` for tenant access | Hardcode tenant IDs |
| Implement proper `TenantResolver` | Trust client-provided tenant IDs |
| Validate tenant access in authorization | Skip middleware on tenant routes |

### 2. Model Design

| ✅ Do | ❌ Don't |
|-------|----------|
| Use `HasTenant` trait on tenant models | Manually filter by tenant in queries |
| Use `$withoutTenantScope` for shared models | Apply tenant scope to lookup tables |
| Index `tenant_id` column | Forget to add tenant_id to new tables |

### 3. Query Patterns

```php
// ✅ Correct - Uses automatic scoping
$users = User::where('active', true)->get();

// ✅ Correct - Explicit bypass for admin/cross-tenant operations
$allUsers = User::withoutTenantScope()->get();

// ❌ Bad - Redundant tenant filter (scope already adds this)
$users = User::where('tenant_id', $tenantId)->get();

// ❌ Bad - Bypassing scope risks accessing other tenant's data
// User with id=1 might belong to a different tenant!
$user = User::withoutTenantScope()->where('id', 1)->first();

// ✅ Correct - Let the scope filter by current tenant
$user = User::find(1);  // Returns null if user doesn't belong to current tenant
```

### 4. Testing

```php
// Always set tenant in tests
public function testFeature(): void
{
    app(TenantContext::class)->setTenantId(1);
    
    // Test code runs in tenant context
}
```

### 5. Laravel Octane

This package is fully compatible with Laravel Octane. The package uses Octane-safe bindings:

```php
// TenantServiceProvider uses scoped binding for TenantContext
$this->app->scoped(TenantContext::class, function (Application $app): TenantContext {
    return new TenantContext($app->make(TenantResolver::class));
});

// Uses bind() for TenantResolver (stateless, but bind() is safer for Octane)
$this->app->bind(TenantResolver::class, ChainTenantResolver::class);
```

| Binding | Used For | Octane Safe | Description |
|---------|----------|-------------|-------------|
| `singleton()` | ❌ Not used | ❌ No | Instance persists across requests, causes data leakage |
| `scoped()` | `TenantContext` | ✅ Yes | Instance is reset for each request |
| `bind()` | `TenantResolver` | ✅ Yes | New instance per resolution (stateless, but extra safe) |

**Why this matters:**
- `TenantContext` uses `scoped()` - each request gets a fresh instance with isolated tenant state
- `TenantResolver` uses `bind()` - creates new instance per resolution (stateless, but ensures no cross-request state)
- No additional configuration needed for Octane
- All tenant data is properly isolated between requests

---

## Testing

### Unit Test Example

```php
use Ouredu\MultiTenant\Tenancy\TenantContext;

class TenantIsolationTest extends TestCase
{
    public function test_queries_are_scoped_to_tenant(): void
    {
        // Arrange
        app(TenantContext::class)->setTenantId(1);
        $record1 = Model::factory()->create();
        
        app(TenantContext::class)->setTenantId(2);
        $record2 = Model::factory()->create();
        
        // Act
        $results = Model::all();
        
        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals($record2->id, $results->first()->id);
    }
    
    public function test_cross_tenant_query_works(): void
    {
        // Arrange
        app(TenantContext::class)->setTenantId(1);
        Model::factory()->create();
        
        app(TenantContext::class)->setTenantId(2);
        Model::factory()->create();
        
        // Act
        $results = Model::withoutTenantScope()->get();
        
        // Assert
        $this->assertCount(2, $results);
    }
}
```

---

## Troubleshooting

### Issue: Empty Query Results

**Symptoms:** Queries return empty even though data exists.

**Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Tenant context not initialized | Ensure `TenantMiddleware` is applied |
| Wrong tenant ID | Verify session/resolver returns correct tenant |
| Model missing scope | Ensure model uses `HasTenant` trait (automatically adds `TenantScope`) |

**Debug:**

```php
$context = app(TenantContext::class);
dump($context->hasTenant());
dump($context->getTenantId());
```

### Issue: Cross-Tenant Data Visible

**Symptoms:** Data from other tenants appears in queries.

**Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Model has `$withoutTenantScope = true` | Remove property if model should be scoped |
| Using `withoutTenantScope()` unnecessarily | Review query and remove bypass |
| Missing global scope | Add `TenantScope` to model |

### Issue: Jobs Losing Tenant Context

**Symptoms:** Queued jobs don't have tenant context.

**Solution:** Store tenant ID in job and restore in handle():

```php
class YourJob implements ShouldQueue
{
    public ?int $tenantId = null;

    public function __construct()
    {
        $this->tenantId = app(TenantContext::class)->getTenantId();
    }

    public function handle(): void
    {
        if ($this->tenantId) {
            app(TenantContext::class)->setTenantId($this->tenantId);
        }
        
        // Job code runs with tenant context
    }
}
```

### Issue: Performance Degradation

**Symptoms:** Slow queries after implementing multi-tenancy.

**Solutions:**

1. Verify `tenant_id` column is indexed
2. Add composite indexes: `(tenant_id, other_column)`
3. Review slow query log for missing indexes

---

## Appendix

### Configuration Options

```php
// config/multi-tenant.php
return [
    // Tenant model class (used by DomainTenantResolver)
    'tenant_model' => \App\Models\Tenant::class,
    
    // Default tenant column name
    'tenant_column' => 'tenant_id',
    
    // Session configuration (UserSessionTenantResolver)
    'session' => [
        'helper' => 'getSession',     // Helper function name
        'tenant_column' => 'tenant_id', // Tenant column on session object
    ],
    
    // Domain configuration (DomainTenantResolver)
    'domain' => [
        'column' => 'domain',  // Domain column on tenant model
    ],
    
    // Tables that require tenant_id (for migration and query listener)
    'tables' => [
        // 'users',
        // 'orders',
    ],
    
    // Query listener (logs queries without tenant_id filter)
    'query_listener' => [
        'enabled' => true,
        'log_channel' => null,  // null = default channel
    ],
];
```

### Class Reference

| Class | Purpose |
|-------|---------|
| `TenantContext` | Central tenant ID state management |
| `TenantScope` | Global query scope |
| `TenantResolver` | Contract for resolution strategy |
| `ChainTenantResolver` | Chains multiple resolvers (default) |
| `UserSessionTenantResolver` | Resolves tenant_id from getSession() |
| `DomainTenantResolver` | Resolves tenant_id by domain query |
| `HasTenant` | Model trait for tenant relationship |
| `TenantMiddleware` | HTTP request middleware |
| `TenantMigrateCommand` | Artisan command to add tenant_id to tables |
| `TenantQueryListener` | Logs queries without tenant_id filter |

### Resolver Registration Quick Reference

| Scenario | Registration Method | Example Use Case |
|----------|---------------------|-----------------|
| **Use defaults only** | No registration needed | Standard web app with session |
| **Add custom to chain** | Bind `ChainTenantResolver` with array | Add JWT/header resolver + keep defaults |
| **Replace completely** | Bind your custom resolver class | Custom resolution logic only |
| **Extend chain class** | Create class extending `ChainTenantResolver` | Reusable custom chain configuration |

---

**Document Version:** 1.0  
**Last Updated:** January 2026  
**Maintainer:** OurEdu Development Team
