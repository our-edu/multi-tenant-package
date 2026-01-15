# Multi-Tenant Architecture Documentation

> © 2026 OurEdu - Multi-Tenant Infrastructure for Laravel Services

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

---

## Architecture Design

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         HTTP Request                             │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      TenantMiddleware                            │
│              (Initializes tenant context early)                  │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                       TenantContext                              │
│                  (Singleton per request)                         │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              TenantResolver (Your Implementation)        │    │
│  │   Session / Domain / Header / CLI / Message Broker       │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                        TenantScope                               │
│            (Global scope on Eloquent models)                     │
│                                                                  │
│     SELECT * FROM users WHERE tenant_id = 'xxx' AND ...         │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                         Database                                 │
│              (Shared database, tenant_id column)                 │
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
- Singleton (one instance per request)
- Lazy-loaded (resolved only when accessed)
- Thread-safe for queue workers

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

**UserSessionTenantResolver** - Gets tenant_id from your `getSession()` helper:
```php
// Reads tenant_id column from session object returned by getSession()
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

---

### 4. HasTenant Trait

Model trait providing tenant relationship and automatic tenant assignment.

**Location:** `src/Traits/HasTenant.php`

**Usage:**

```php
use Ouredu\MultiTenant\Traits\HasTenant;
use Ouredu\MultiTenant\Tenancy\TenantScope;

class Payment extends Model
{
    use HasTenant;
    
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
```

**Features:**
- `tenant()` - BelongsTo relationship
- `scopeForTenant($query, $tenantId)` - Query scope
- Automatic `tenant_id` assignment on create/update
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
│   ├── Contracts/
│   │   └── TenantResolver.php    # Interface for tenant resolution
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
composer require ouredu/multi-tenant
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="Ouredu\MultiTenant\Providers\TenantServiceProvider" --tag=config
```

### Step 3: Implement TenantResolver

```php
// app/Providers/TenantServiceProvider.php
use Ouredu\MultiTenant\Contracts\TenantResolver;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantResolver::class, SessionTenantResolver::class);
    }
}
```

### Step 4: Add Middleware

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'tenant' => \Ouredu\MultiTenant\Middleware\TenantMiddleware::class,
];
```

### Step 5: Update Models

```php
use Ouredu\MultiTenant\Traits\HasTenant;
use Ouredu\MultiTenant\Tenancy\TenantScope;

class YourModel extends Model
{
    use HasTenant;
    
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }
}
```

### Step 6: Add Database Column

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

// ✅ Correct - Explicit bypass for admin
$allUsers = User::withoutTenantScope()->get();

// ❌ Wrong - Redundant tenant filter
$users = User::where('tenant_id', $tenantId)->get();

// ❌ Wrong - Bypassing without reason
$users = User::withoutTenantScope()->where('id', 1)->first();
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
| Model missing scope | Add `TenantScope` to model's `booted()` method |

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
        'tenant_column' => null,  // Defaults to tenant_column
    ],
    
    // Domain configuration (DomainTenantResolver)
    'domain' => [
        'column' => 'domain',  // Domain column on tenant model
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

---

**Document Version:** 2.0  
**Last Updated:** January 2026  
**Maintainer:** OurEdu Development Team

