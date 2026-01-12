# Multi-Tenant Architecture Refactoring Documentation

**Project:** Payment Backend System  
**Date:** January 2026  
**Version:** 1.0

---

## Table of Contents

1. [Executive Summary](#executive-summary)  
2. [Overview](#overview)  
3. [Architecture Design](#architecture-design)  
4. [Implementation Details](#implementation-details)  
5. [Migration Guide](#migration-guide)  
6. [Usage Examples](#usage-examples)  
7. [Best Practices](#best-practices)  
8. [Testing](#testing)  
9. [Troubleshooting](#troubleshooting)  
10. [Future Enhancements](#future-enhancements)

---

## Executive Summary

This document describes the multi-tenant refactoring implemented for the Payment Backend System. The refactoring introduces automatic tenant isolation using the `Tenant` table as the tenant identifier. Each tenant has one school, and each school has multiple branches. All database queries are automatically scoped to the current tenant, ensuring data isolation and security.

**Architecture:** Tenant → has one → School → has many → Branches → has many → Models

### Key Benefits

- **Automatic Data Isolation**: All queries are automatically filtered by tenant  
- **Security**: Prevents cross-tenant data access  
- **Minimal Code Changes**: Existing code continues to work with minimal modifications  
- **Flexible**: Models can opt-out of tenant scoping when needed  
- **Backward Compatible**: Existing functionality preserved

---

## Overview

### What is Multi-Tenancy?

Multi-tenancy is an architecture where a single instance of the application serves multiple tenants (customers/organizations). Each tenant's data is isolated and invisible to other tenants.

### Current Implementation

The system uses a **Tenant** model as the tenant identifier. Each tenant has one school, and each school has multiple branches.

**Architecture:**
- **Tenant** = The organization  
- **School** = Belongs to a Tenant (one-to-one relationship)  
- **Branch** = Part of a School (one-to-many relationship)  
- **User** = Belongs to a Branch within a School

The tenant context is determined directly from the user's session:

- `UserSession.tenant_id` → contains the Tenant UUID (set during login)

### Architecture Pattern

We've implemented a **Shared Database, Shared Schema** pattern with **Row-Level Security**:

- All tenants share the same database and schema  
- Each row has a `tenant_id` column  
- Queries are automatically filtered by `tenant_id`

---

## Architecture Design

### Core Components

#### 1. TenantContext Service

**Original Location in Payment Service:** `src/Support/Tenancy/TenantContext.php`

Manages the current tenant context throughout the request lifecycle.

**Key Methods:**

- `getTenant()`: Returns the current Tenant model  
- `getTenantId()`: Returns the current tenant UUID  
- `setTenant(Tenant $tenant)`: Manually set tenant (for testing/admin)  
- `setTenantId(string $tenantId)`: Manually set tenant ID (for testing/admin)  
- `hasTenant()`: Check if tenant is set  
- `clear()`: Clear tenant context

**How it works:**

- Gets `tenant_id` directly from `UserSession.tenant_id` (set during login)  
- Loads Tenant model from database  
- Singleton service (one instance per request)  
- Lazy-loaded (only resolved when accessed)

> **Note (Package Version):**  
> In this package, the resolution strategy is abstracted behind `TenantResolver`. Each service provides its own implementation (session, domain, CLI, etc.), but the core behavior remains the same.

#### 2. TenantScope (Global Scope)

**Original Location in Payment Service:** `src/Support/Tenancy/TenantScope.php`

Automatically applies tenant filtering to all Eloquent queries.

**Features:**

- Automatically adds `WHERE tenant_id = ?` to queries  
- Can be bypassed using `withoutTenantScope()`  
- Can query specific tenant using `forTenant($tenantId)`  
- Respects model opt-out flags

#### 3. HasTenant Trait

**Original Location in Payment Service:** `src/Support/Traits/HasTenant.php`  
**Package Location:** `src/Traits/HasTenant.php`

Provides tenant relationship and helper methods for models.

**Features:**

- `tenant()` relationship method (returns Tenant model)  
- `scopeForTenant()` query scope  
- **Automatic tenant assignment on model creation** (sets tenant UUID)  
- **Automatic tenant assignment on model update** (sets tenant UUID if missing)

**Important:**  
The trait automatically sets `tenant_id` on both `creating` and `updating` events. This ensures that:
- All new records get `tenant_id` from the current tenant context  
- Updated records get `tenant_id` if it was missing (e.g., from old data)

#### 4. TenantMiddleware

**Original Location in Payment Service:** `src/Support/Middleware/TenantMiddleware.php`  
**Package Location:** `src/Middleware/TenantMiddleware.php`

Ensures tenant context is initialized early in request lifecycle.

**Registration (example in a service):**

```php
// In HttpKernel.php
protected $middlewareAliases = [
    // ...
    'tenant' => \Oured\MultiTenant\Middleware\TenantMiddleware::class,
];
```

Applied to routes that should be tenant-aware (e.g. all API routes).

#### 5. BaseModel Updates

**Original Location in Payment Service:** `src/Support/BaseModel.php`

All models extending `BaseModel` automatically:

- Use `HasTenant` trait  
- Apply `TenantScope` globally  
- Can opt-out by setting `$withoutTenantScope = true`

In the package, the same behavior is available by:

- Using `HasTenant` on the model  
- Optionally configuring global scope in your own base model

---

## Implementation Details

### File Structure (Original Service)

```text
core/
├── src/
│   ├── Support/
│   │   ├── Tenancy/
│   │   │   ├── TenantContext.php          # Tenant context service
│   │   │   └── TenantScope.php            # Global scope for queries
│   │   ├── Traits/
│   │   │   └── HasTenant.php              # Tenant trait for models
│   │   ├── Middleware/
│   │   │   └── TenantMiddleware.php       # Tenant middleware
│   │   ├── Providers/
│   │   │   └── TenantServiceProvider.php  # Service provider
│   │   └── BaseModel.php                  # Updated base model
│   └── App/
│       └── BaseApp/
│           └── HttpKernel.php             # Middleware registration
├── database/
│   └── migrations/
│       └── 2025_01_15_000000_add_tenant_id_to_tables.php
└── config/
    └── app.php                            # Service provider registration
```

### Database Schema Changes

#### Migration: `add_tenant_id_to_tables`

**Purpose:** Adds `tenant_id` column to all tenant-scoped tables.

**Tables Modified (excerpt):**

- payments  
- payment_items  
- advance_payments  
- advance_payment_items  
- advance_payment_withdraws  
- refunds  
- opening_balances  
- withdraws  
- dropouts  
- student_transfer_requests  
- student_service_subscriptions  
- service_enrollments  
- service_enrollment_school_subscriptions  
- service_enrollment_bus_subscriptions  
- account_statements  
- receipts  
- discounts  
- ... (see migration file for complete list)

**Column Details:**

```sql
tenant_id UUID NULLABLE
INDEX(tenant_id)
-- The tenant_id is just a UUID reference to the tenant table
```

**Data Migration:**

To be implemented per project to populate `tenant_id` for existing records based on:

- Flow: Model → Student → Tenant  
- Use the project’s actual data structure to derive `tenant_id`.

### Service Provider Registration (Original Service)

**File:** `config/app.php`

```php
'providers' => [
    // ...
    \Support\Providers\TenantServiceProvider::class,
],
```

In the package, `Oured\MultiTenant\Providers\TenantServiceProvider` is auto-discovered by Laravel and can be added manually if needed.

### Middleware Registration (Original Service)

**File:** `src/App/BaseApp/HttpKernel.php`

```php
protected $middlewareAliases = [
    // ...
    'tenant' => TenantMiddleware::class,
];
```

Applied (for example) to:

- All API routes  
- After the session/header middleware so tenant can be resolved

---

## Migration Guide (Original Service)

### Step 1: Review Migration File

1. Open `database/migrations/2025_01_15_000000_add_tenant_id_to_tables.php`  
2. Review the `$tables` array  
3. Add/remove tables based on your requirements  
4. Update `populateTenantIds` for your data structure

### Step 2: Run Migration

```bash
php artisan migrate
```

**Important:**

- Backup your database before running  
- Test in staging environment first  
- Review the data population logic

### Step 3: Verify Data

After migration, verify:

- All relevant tables have `tenant_id` column  
- Existing records have `tenant_id` populated (as intended)

### Step 4: Handle Bulk Operations

For bulk insert/update operations using `DB::table()->insert()` or `DB::table()->update()`, you must manually add `tenant_id`:

```php
use Support\Tenancy\TenantContext;

$tenantContext = app(TenantContext::class);
$tenantId = $tenantContext->getTenantId();

// Bulk insert with tenant_id
DB::table('some_table')->insert([
    [
        'uuid' => Str::uuid(),
        'tenant_id' => $tenantId, // Must add manually
        'name' => 'Example',
        // ... other fields
    ],
    // ... more records
]);

// Bulk update with tenant_id
DB::table('some_table')
    ->where('some_condition', 'value')
    ->update([
        'tenant_id' => $tenantId, // Must add manually
        'updated_field' => 'new_value',
    ]);
```

**Best Practice:** Prefer using Eloquent models instead of direct DB queries when possible, as they automatically handle `tenant_id`.

### Step 5: Update Models (If Needed)

Most models extending `BaseModel` will work automatically. However, some models might need to opt out.

**Models that should NOT have tenant scope (example):**

- `School` (tenant itself)  
- `Branch` (belongs to school, but needs cross-school queries)  
- `User` (may be shared across tenants)  
- `Role` (may be shared)  
- `Permission` (may be shared)

**To opt-out a model:**

```php
class School extends BaseModel
{
    public $withoutTenantScope = true; // School is the tenant itself
}

class Branch extends BaseModel
{
    public $withoutTenantScope = true; // Branches belong to school, queried across school
}
```

### Step 6: Update Login Logic

**Important:** You must set `tenant_id` when creating `UserSession` during login.

**Example login code:**

```php
// When user logs in, create session with tenant_id
$branch = Branch::find($branchId);
$school = $branch->school;
$tenantId = $school->tenant_id; // Get tenant_id from school

$session = UserSession::create([
    'user_uuid' => $user->uuid,
    'role_id' => $role->id,
    'branch_uuid' => $branch->uuid,
    'tenant_id' => $tenantId, // Set tenant_id
    'academic_year_uuid' => $academicYear->uuid,
    'token' => $token,
    'is_valid' => true,
]);
```

Or if you already have the tenant:

```php
$tenant = Tenant::find($tenantId);

$session = UserSession::create([
    'user_uuid' => $user->uuid,
    'role_id' => $role->id,
    'branch_uuid' => $branch->uuid,
    'tenant_id' => $tenant->uuid,
    // ... other fields
]);
```

### Step 7: Update Existing Queries

**Before:**

```php
$students = Student::where('branch_id', $branchId)->get();
```

**After:**

```php
// Automatic - no changes needed if using TenantScope + HasTenant
$students = Student::all(); // Automatically filtered by tenant
```

**For cross-tenant queries (admin operations):**

```php
$allStudents = Student::withoutTenantScope()->get();
```

### Step 8: Update Tests

Update test cases to set tenant context:

```php
use Support\Tenancy\TenantContext;

$tenant = Branch::factory()->create();
app(TenantContext::class)->setTenant($tenant);

// Now queries will be scoped to this tenant
```

---

## Usage Examples (Original Service)

### Basic Usage

#### Automatic Tenant Scoping

```php
// All queries automatically filtered by tenant
$students = Student::all(); // Only students for current tenant
$payments = Payment::where('paid', true)->get(); // Only tenant's payments
```

#### Creating Records

```php
// Tenant is automatically set on creation
$student = Student::create([
    'user_id' => $userId,
    'grade_class_id' => $gradeId,
    // tenant_id is automatically set from TenantContext
]);
```

#### Accessing Tenant Relationship

```php
$student = Student::first();
$tenant = $student->tenant; // Returns Tenant model

// Access branch
$branch = $student->branch; // Returns Branch model

// Get tenant details
$tenantName = $student->tenant->name;
$tenantDomain = $student->tenant->domain;
```

### Advanced Usage

#### Querying Without Tenant Scope

```php
// For admin operations or cross-tenant queries
$allStudents = Student::withoutTenantScope()->get();
```

#### Querying Specific Tenant

```php
// Query records for a specific tenant (UUID)
$tenantId = 'some-tenant-uuid';
$students = Student::forTenant($tenantId)->get();

// Or using Tenant model
$tenant = Tenant::find($tenantId);
$students = Student::forTenant($tenant)->get();
```

#### Manual Tenant Assignment

```php
// Set tenant explicitly (useful for testing or admin operations)
$tenant = Tenant::find($tenantId);
app(TenantContext::class)->setTenant($tenant);

// Or set by ID
app(TenantContext::class)->setTenantId($tenantId);

// Now all queries will use this tenant
```

#### Checking Tenant Context

```php
$tenantContext = app(TenantContext::class);

if ($tenantContext->hasTenant()) {
    $tenant = $tenantContext->getTenant(); // Returns Tenant model
    $tenantId = $tenantContext->getTenantId(); // Returns tenant UUID (string)
    
    // Access tenant properties
    $tenantName = $tenant->name;
    $tenantDomain = $tenant->domain;
}
```

### Custom Tenant Column

If a model uses a different column name:

```php
class Payment extends BaseModel
{
    protected $tenantColumn = 'branch_uuid'; // Instead of tenant_id
}
```

### Opting Out Models

```php
class Branch extends BaseModel
{
    public $withoutTenantScope = true; // No tenant filtering
}
```

---

## Best Practices

### 1. Always Use Tenant Context

- Never hardcode tenant IDs in queries  
- Let the system handle tenant scoping automatically  
- Use `withoutTenantScope()` only when absolutely necessary

### 2. Model Design

- All tenant-scoped models should extend your base model and/or use `HasTenant`  
- Use `$withoutTenantScope = true` only for shared models  
- Use custom `$tenantColumn` or `getTenantColumn()` if column name differs

### 3. Testing

- Always set tenant context in tests  
- Test both tenant-scoped and cross-tenant scenarios  
- Verify data isolation between tenants

### 4. Data Migration

- Always populate `tenant_id` for existing records  
- Verify data integrity after migration  
- Test migration in staging first

### 5. Performance

- Index `tenant_id` column  
- Consider composite indexes: `(tenant_id, other_column)`  
- Monitor query performance after implementation

### 6. Security

- Never trust client-provided tenant IDs  
- Always use tenant from authenticated session or trusted resolver  
- Validate tenant access in authorization logic

---

## Testing

### Unit Tests (Example)

```php
use Support\Tenancy\TenantContext;
use Domain\Models\Tenant\Tenant;
use Domain\Models\Student\Student;

class StudentTest extends TestCase
{
    public function test_student_queries_are_scoped_to_tenant()
    {
        // Create two tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        // Set tenant 1
        app(TenantContext::class)->setTenant($tenant1);
        
        // Create students for tenant 1
        $student1 = Student::factory()->create();
        
        // Switch to tenant 2
        app(TenantContext::class)->setTenant($tenant2);
        
        // Create students for tenant 2
        $student2 = Student::factory()->create();
        
        // Query should only return tenant 2's students
        $students = Student::all();
        $this->assertCount(1, $students);
        $this->assertEquals($student2->uuid, $students->first()->uuid);
    }
    
    public function test_cross_tenant_query()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        app(TenantContext::class)->setTenant($tenant1);
        Student::factory()->create();
        
        app(TenantContext::class)->setTenant($tenant2);
        Student::factory()->create();
        
        // Query without tenant scope should return all
        $allStudents = Student::withoutTenantScope()->get();
        $this->assertCount(2, $allStudents);
    }
}
```

### Integration Tests (Example)

```php
public function test_api_returns_only_tenant_data()
{
    $tenant = Tenant::factory()->create();
    $branch = Branch::factory()->create();
    $user = User::factory()->create();
    
    // Create session with tenant_id
    $session = UserSession::factory()->create([
        'user_uuid' => $user->uuid,
        'branch_uuid' => $branch->uuid,
        'tenant_id' => $tenant->uuid,
    ]);
  
    // Create data for this tenant
    $student = Student::factory()->create();
  
    // Make API request
    $response = $this->withHeader('Session-Key', encrypt($session->uuid))
        ->getJson('/api/v1/students');
  
    $response->assertOk();
    $response->assertJsonCount(1, 'data');
}
```

---

## Troubleshooting

### Issue: Queries returning empty results

**Cause:** Tenant context not set or incorrect tenant ID.

**Solution:**

1. Check that `TenantMiddleware` is applied to routes  
2. Verify session or resolver is providing a valid tenant  
3. Check tenant context: `app(TenantContext::class)->getTenant()`

### Issue: Cross-tenant data visible

**Cause:** Model not using tenant scope or scope bypassed.

**Solution:**

1. Verify model uses `HasTenant` / global scope  
2. Check model doesn't have `$withoutTenantScope = true` (if you use that pattern)  
3. Review queries for `withoutTenantScope()` usage

### Issue: Migration fails

**Cause:** Table structure differences or data conflicts.

**Solution:**

1. Review migration file for your table structure  
2. Check for existing `tenant_id` columns  
3. Verify foreign key constraints

### Issue: Performance degradation

**Cause:** Missing indexes on `tenant_id`.

**Solution:**

1. Verify migration created indexes  
2. Add composite indexes for common query patterns  
3. Review slow query logs

---

## Future Enhancements

### Potential Improvements

1. **Tenant-specific Database Connections**
   - Separate databases per tenant for better isolation  
   - Requires connection switching logic

2. **Tenant-specific Configuration**
   - Per-tenant settings (timezone, locale, etc.)  
   - Tenant-specific feature flags

3. **Tenant Analytics**
   - Track tenant usage and performance  
   - Tenant-specific reporting

4. **Tenant Management UI**
   - Admin interface for tenant management  
   - Tenant onboarding workflows

5. **Automatic Tenant Provisioning**
   - API for creating new tenants  
   - Automated setup processes

---

## Appendix

### A. Files Modified (Original Payment Service)

1. `src/Support/BaseModel.php` - Added tenant scope  
2. `src/Support/Tenancy/TenantContext.php` - New file  
3. `src/Support/Tenancy/TenantScope.php` - New file  
4. `src/Support/Traits/HasTenant.php` - New file  
5. `src/Support/Middleware/TenantMiddleware.php` - New file  
6. `src/Support/Providers/TenantServiceProvider.php` - New file  
7. `src/App/BaseApp/HttpKernel.php` - Registered middleware  
8. `src/Support/RouteAttributes/RouteAttributesServiceProvider.php` - Added middleware  
9. `config/app.php` - Registered service provider  
10. `database/migrations/2025_01_15_000000_add_tenant_id_to_tables.php` - New migration

### B. Key Classes Reference

| Class                     | Purpose                | Notes                                    |
| ------------------------- | ---------------------- | ---------------------------------------- |
| `TenantContext`         | Manages tenant context | Works with Tenant model / resolver       |
| `TenantScope`           | Global query scope     | Filters by tenant_id UUID                |
| `HasTenant`             | Model trait            | Provides tenant relationship & helpers   |
| `TenantMiddleware`      | Request middleware     | Initializes tenant context               |
| `TenantServiceProvider` | Service registration   | Registers tenant services                |
| `Tenant`                | Tenant model           | The tenant entity                        |

### C. Migration Checklist

- [ ] Review migration file table list  
- [ ] Backup production database  
- [ ] Test migration in staging  
- [ ] Run migration in production  
- [ ] Verify data population  
- [ ] Update models if needed  
- [ ] Update tests  
- [ ] Monitor performance  
- [ ] Update documentation

### D. Support Contacts

For questions or issues related to this refactoring, contact:

- Development Team Lead  
- System Architect  
- Database Administrator

---

**Document Version:** 1.0  
**Last Updated:** January 2025  
**Author:** Development Team


