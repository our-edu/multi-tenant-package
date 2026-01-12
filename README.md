# multi-tenant

Reusable multi-tenant infrastructure for OurEdu Laravel services.

This package extracts the **tenant context**, **global tenant scope**, **model trait**, and **middleware** into a single Composer package that can be installed in any service.

It is intentionally generic: each service decides *how* to resolve the tenant (from session, domain, CLI, etc.) by providing its own `TenantResolver` implementation.

---

## Installation

### 1. Add the package (path repository)

In your service `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../multi-tenant-package"
    }
  ],
  "require": {
    "ouredu/multi-tenant": "*"
  }
}
```

Then:

```bash
composer update ouredu/multi-tenant
```

### 2. Publish config (optional)

```bash
php artisan vendor:publish --provider=\"Oured\\MultiTenant\\Providers\\TenantServiceProvider\" --tag=config
```

This will create `config/multi-tenant.php` in your service.

---

## Core Concepts

### TenantContext

`Oured\MultiTenant\Tenancy\TenantContext`

- Caches the current tenant model for the current request / job / command.
- Provides:
  - `getTenant(): ?Model`
  - `getTenantId(): ?string`
  - `hasTenant(): bool`
  - `setTenant(?Model $tenant): void`
  - `clear(): void`

It **does not** know how to resolve the tenant by itself — it delegates that to a `TenantResolver` that you implement in each service.

### TenantResolver (you implement this)

`Oured\MultiTenant\Contracts\TenantResolver`

You must bind an implementation in your service, for example in a service provider:

```php
use Illuminate\Support\ServiceProvider;
use Oured\MultiTenant\Contracts\TenantResolver;
use Oured\MultiTenant\Tenancy\TenantContext;

class AppTenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantResolver::class, function () {
            return new class implements TenantResolver {
                public function resolveTenant(): ?\Illuminate\Database\Eloquent\Model
                {
                    // Example: resolve from UserSession.tenant_id
                    $session = getSession(); // your existing helper
                    if (! $session || ! $session->tenant_id) {
                        return null;
                    }

                    return \Domain\Models\Tenant\Tenant::find($session->tenant_id);
                }
            };
        });
    }
}
```

You can also implement resolution from **domain**, **CLI arguments**, etc.

---

## Model Integration

### 1. Use the HasTenant trait

In any model that should be tenant-scoped:

```php
use Illuminate\Database\Eloquent\Model;
use Oured\MultiTenant\Traits\HasTenant;

class Payment extends Model
{
    use HasTenant;

    // Optional: custom tenant column
    public function getTenantColumn(): string
    {
        return 'tenant_id'; // or 'branch_uuid', etc.
    }
}
```

Features:
- Automatically applies the `TenantScope` global scope.
- Automatically sets `tenant_id` on `creating` and `updating` if missing (from `TenantContext`).
- Provides:
  - `tenant()` relationship
  - `scopeForTenant($query, string $tenantId)`

---

## Middleware

`Oured\MultiTenant\Middleware\TenantMiddleware`

Register it in your kernel (or via attributes) and use it on routes that should have tenant context:

```php
// In HttpKernel.php
protected $middlewareAliases = [
    // ...
    'tenant' => \Oured\MultiTenant\Middleware\TenantMiddleware::class,
];

// In routes/api.php
Route::middleware(['tenant'])->group(function () {
    Route::get('/payments', [PaymentController::class, 'index']);
});
```

The middleware just triggers `TenantContext::getTenant()` early; the real logic is in your `TenantResolver`.

---

## Configuration

`config/multi-tenant.php`:

- `tenant_model`: the class name of your Tenant model.
- `tenant_column`: default tenant column name when a model does not define `getTenantColumn()`.

Example:

```php
return [
    'tenant_model' => \Domain\Models\Tenant\Tenant::class,
    'tenant_column' => 'tenant_id',
];
```

---

## How to Use in Other Services

1. Add this package as a path repository and require it.
2. Publish and adjust `config/multi-tenant.php`.
3. Implement and bind a `TenantResolver` that matches that service:
   - From `UserSession.tenant_id`
   - From request domain/subdomain
   - From CLI option (for commands)
4. Add `HasTenant` to all tenant-scoped models.
5. Use `tenant` middleware on API routes that must be tenant-aware.

This keeps all the core multi-tenant logic (context, scope, trait, middleware) **in one place**, while letting each service plug in its own tenant resolution rules.
