# Tenant Resolution Strategies

> © 2026 OurEdu - Guide for handling tenant resolution in various contexts

This document explains how to handle tenant resolution in scenarios where there is no user session, such as:

- Public routes (no authentication)
- Queued jobs
- Event listeners (message broker consumers)
- Artisan commands
- Webhooks
- Scheduled tasks

---

## Table of Contents

1. [Overview](#overview)
2. [Resolution Strategy Pattern](#resolution-strategy-pattern)
3. [Implementation Examples](#implementation-examples)
   - [Session-Based Resolution](#1-session-based-resolution)
   - [Domain/Subdomain Resolution](#2-domainsubdomain-resolution)
   - [Header-Based Resolution](#3-header-based-resolution)
   - [Job/Queue Resolution](#4-jobqueue-resolution)
   - [Command Resolution](#5-command-resolution)
   - [Webhook Resolution](#6-webhook-resolution)
   - [Event/Message Broker Resolution](#7-eventmessage-broker-resolution)
   - [Composite Resolution](#8-composite-resolution-recommended)
4. [Best Practices](#best-practices)
5. [Testing Strategies](#testing-strategies)

---

## Overview

The multi-tenant package uses a **TenantResolver** interface that each service must implement. This allows maximum flexibility - you decide HOW to resolve the tenant based on your application's needs.

```php
interface TenantResolver
{
    public function resolveTenant(): ?Model;
}
```

The key insight is: **different contexts require different resolution strategies**.

---

## Resolution Strategy Pattern

We recommend using a **Composite Resolver** that tries multiple strategies in order:

```
Request Context:
  1. Try session/auth user
  2. Try request header (X-Tenant-ID)
  3. Try domain/subdomain
  4. Return null (public route)

Job Context:
  1. Check job payload for tenant_id
  2. Return null or throw exception

Command Context:
  1. Check --tenant option
  2. Return null (admin command)

Message Broker Context:
  1. Check message metadata for tenant_id
  2. Return null or throw exception
```

---

## Implementation Examples

### 1. Session-Based Resolution

For authenticated routes where tenant comes from user session:

```php
<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Oured\MultiTenant\Contracts\TenantResolver;

class SessionTenantResolver implements TenantResolver
{
    public function resolveTenant(): ?Model
    {
        // Get session from your session service
        $session = getSession(); // Your helper function
        
        if (! $session || ! $session->tenant_id) {
            return null;
        }

        return Tenant::find($session->tenant_id);
    }
}
```

---

### 2. Domain/Subdomain Resolution

For public routes where tenant is determined by domain:

```php
<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Oured\MultiTenant\Contracts\TenantResolver;

class DomainTenantResolver implements TenantResolver
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function resolveTenant(): ?Model
    {
        $host = $this->request->getHost();
        
        // Example: tenant1.example.com -> extract "tenant1"
        $subdomain = explode('.', $host)[0] ?? null;
        
        if (! $subdomain || $subdomain === 'www') {
            return null;
        }

        return Tenant::where('subdomain', $subdomain)->first();
    }
}
```

---

### 3. Header-Based Resolution

For API requests where tenant is passed via header:

```php
<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Oured\MultiTenant\Contracts\TenantResolver;

class HeaderTenantResolver implements TenantResolver
{
    private const TENANT_HEADER = 'X-Tenant-ID';

    public function __construct(
        private readonly Request $request
    ) {}

    public function resolveTenant(): ?Model
    {
        $tenantId = $this->request->header(self::TENANT_HEADER);
        
        if (! $tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }
}
```

---

### 4. Job/Queue Resolution

Jobs need special handling because they run outside the HTTP request context.

#### Step 1: Create a Tenant-Aware Job Trait

```php
<?php

namespace App\Tenancy\Traits;

use Oured\MultiTenant\Tenancy\TenantContext;

trait TenantAwareJob
{
    /**
     * The tenant ID for this job.
     */
    public ?string $tenantId = null;

    /**
     * Set the tenant ID before dispatching.
     */
    public function forTenant(string $tenantId): static
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Capture current tenant when job is created.
     */
    public function __construct()
    {
        // Auto-capture tenant from current context
        $context = app(TenantContext::class);
        $this->tenantId = $context->getTenantId();
    }

    /**
     * Initialize tenant context before job executes.
     */
    protected function initializeTenantContext(): void
    {
        if ($this->tenantId) {
            $tenant = \App\Models\Tenant::find($this->tenantId);
            app(TenantContext::class)->setTenant($tenant);
        }
    }
}
```

#### Step 2: Create Job Middleware

```php
<?php

namespace App\Tenancy\Middleware;

use App\Models\Tenant;
use Closure;
use Oured\MultiTenant\Tenancy\TenantContext;

class SetTenantForJob
{
    public function handle(object $job, Closure $next): void
    {
        // Check if job has tenant_id property
        if (property_exists($job, 'tenantId') && $job->tenantId) {
            $tenant = Tenant::find($job->tenantId);
            
            if ($tenant) {
                app(TenantContext::class)->setTenant($tenant);
            }
        }

        $next($job);

        // Clear tenant context after job completes
        app(TenantContext::class)->clear();
    }
}
```

#### Step 3: Use in Jobs

```php
<?php

namespace App\Jobs;

use App\Tenancy\Middleware\SetTenantForJob;
use App\Tenancy\Traits\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TenantAwareJob;

    public function __construct(
        public readonly string $paymentId
    ) {
        // TenantAwareJob captures tenant automatically
        $this->captureTenant();
    }

    /**
     * Job middleware.
     */
    public function middleware(): array
    {
        return [new SetTenantForJob()];
    }

    public function handle(): void
    {
        // Tenant context is already set by middleware
        // All queries will be scoped to the tenant
        $payment = Payment::find($this->paymentId);
        // ...
    }

    private function captureTenant(): void
    {
        $context = app(\Oured\MultiTenant\Tenancy\TenantContext::class);
        $this->tenantId = $context->getTenantId();
    }
}

// Dispatching:
ProcessPayment::dispatch($paymentId); // Auto-captures current tenant

// Or explicitly:
ProcessPayment::dispatch($paymentId)->forTenant($specificTenantId);
```

---

### 5. Command Resolution

For Artisan commands, use command options or arguments:

#### Step 1: Create Command Resolver

```php
<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Oured\MultiTenant\Contracts\TenantResolver;

class CommandTenantResolver implements TenantResolver
{
    private static ?string $tenantId = null;

    public static function setTenantId(?string $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function getTenantId(): ?string
    {
        return self::$tenantId;
    }

    public function resolveTenant(): ?Model
    {
        if (! self::$tenantId) {
            return null;
        }

        return Tenant::find(self::$tenantId);
    }
}
```

#### Step 2: Create Tenant-Aware Command Trait

```php
<?php

namespace App\Tenancy\Traits;

use App\Models\Tenant;
use App\Tenancy\Resolvers\CommandTenantResolver;
use Oured\MultiTenant\Tenancy\TenantContext;
use Symfony\Component\Console\Input\InputOption;

trait TenantAwareCommand
{
    /**
     * Add tenant option to command.
     */
    protected function addTenantOption(): void
    {
        $this->addOption(
            'tenant',
            't',
            InputOption::VALUE_OPTIONAL,
            'The tenant ID to run this command for'
        );
    }

    /**
     * Initialize tenant for command execution.
     */
    protected function initializeTenant(): ?Tenant
    {
        $tenantId = $this->option('tenant');

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant not found: {$tenantId}");
            return null;
        }

        // Set in context
        app(TenantContext::class)->setTenant($tenant);
        
        // Set in resolver for any new resolutions
        CommandTenantResolver::setTenantId($tenantId);

        $this->info("Running for tenant: {$tenant->name}");

        return $tenant;
    }

    /**
     * Run callback for each tenant.
     */
    protected function forEachTenant(callable $callback): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");
            
            app(TenantContext::class)->setTenant($tenant);
            
            try {
                $callback($tenant);
            } catch (\Exception $e) {
                $this->error("Error for tenant {$tenant->name}: {$e->getMessage()}");
            } finally {
                app(TenantContext::class)->clear();
            }
        }
    }
}
```

#### Step 3: Use in Commands

```php
<?php

namespace App\Console\Commands;

use App\Tenancy\Traits\TenantAwareCommand;
use Illuminate\Console\Command;

class ProcessTenantReports extends Command
{
    use TenantAwareCommand;

    protected $signature = 'reports:process {--tenant= : Specific tenant ID}';
    protected $description = 'Process reports for tenant(s)';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            // Run for specific tenant
            $tenant = $this->initializeTenant();
            if (! $tenant) {
                return self::FAILURE;
            }
            
            $this->processReports();
        } else {
            // Run for all tenants
            $this->forEachTenant(function ($tenant) {
                $this->processReports();
            });
        }

        return self::SUCCESS;
    }

    private function processReports(): void
    {
        // All queries are scoped to current tenant
        $reports = Report::all();
        // ...
    }
}

// Usage:
// php artisan reports:process --tenant=uuid-here
// php artisan reports:process  # All tenants
```

---

### 6. Webhook Resolution

For webhooks from external services:

```php
<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Oured\MultiTenant\Contracts\TenantResolver;

class WebhookTenantResolver implements TenantResolver
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function resolveTenant(): ?Model
    {
        // Strategy 1: Tenant ID in URL path
        // /webhooks/{tenant_id}/stripe
        $tenantId = $this->request->route('tenant_id');
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        // Strategy 2: Tenant ID in query string
        // /webhooks/stripe?tenant_id=xxx
        $tenantId = $this->request->query('tenant_id');
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        // Strategy 3: Lookup by webhook metadata
        // For Stripe, lookup by connected account ID
        $payload = $this->request->all();
        if (isset($payload['account'])) {
            return Tenant::where('stripe_account_id', $payload['account'])->first();
        }

        return null;
    }
}
```

#### Webhook Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Oured\MultiTenant\Tenancy\TenantContext;

class WebhookController extends Controller
{
    public function handleStripeWebhook(Request $request, string $tenantId)
    {
        // Manually set tenant for webhook processing
        $tenant = Tenant::findOrFail($tenantId);
        app(TenantContext::class)->setTenant($tenant);

        try {
            // Process webhook with tenant context
            $this->processWebhook($request);
            
            return response()->json(['status' => 'success']);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}

// Route: POST /webhooks/{tenant_id}/stripe
```

---

### 7. Event/Message Broker Resolution

For consuming messages from RabbitMQ, Kafka, etc.:

#### Step 1: Message DTO with Tenant

```php
<?php

namespace App\Messages;

class TenantMessage
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $eventType,
        public readonly array $payload,
        public readonly array $metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            eventType: $data['event_type'],
            payload: $data['payload'] ?? [],
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'metadata' => $this->metadata,
        ];
    }
}
```

#### Step 2: Message Publisher

```php
<?php

namespace App\Services;

use App\Messages\TenantMessage;
use Oured\MultiTenant\Tenancy\TenantContext;

class MessagePublisher
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly mixed $broker // Your broker client
    ) {}

    /**
     * Publish message with current tenant context.
     */
    public function publish(string $eventType, array $payload, string $queue = 'default'): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        if (! $tenantId) {
            throw new \RuntimeException('Cannot publish message without tenant context');
        }

        $message = new TenantMessage(
            tenantId: $tenantId,
            eventType: $eventType,
            payload: $payload,
            metadata: [
                'published_at' => now()->toIso8601String(),
                'source' => config('app.name'),
            ]
        );

        $this->broker->publish($queue, json_encode($message->toArray()));
    }

    /**
     * Publish message for specific tenant.
     */
    public function publishForTenant(string $tenantId, string $eventType, array $payload, string $queue = 'default'): void
    {
        $message = new TenantMessage(
            tenantId: $tenantId,
            eventType: $eventType,
            payload: $payload,
            metadata: [
                'published_at' => now()->toIso8601String(),
                'source' => config('app.name'),
            ]
        );

        $this->broker->publish($queue, json_encode($message->toArray()));
    }
}
```

#### Step 3: Message Consumer

```php
<?php

namespace App\Services;

use App\Messages\TenantMessage;
use App\Models\Tenant;
use Oured\MultiTenant\Tenancy\TenantContext;

class MessageConsumer
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Process a message with tenant context.
     */
    public function consume(string $rawMessage): void
    {
        $data = json_decode($rawMessage, true);
        $message = TenantMessage::fromArray($data);

        // Set tenant context
        $tenant = Tenant::findOrFail($message->tenantId);
        $this->tenantContext->setTenant($tenant);

        try {
            $this->handleMessage($message);
        } finally {
            // Always clear context after processing
            $this->tenantContext->clear();
        }
    }

    private function handleMessage(TenantMessage $message): void
    {
        // Route to appropriate handler
        match ($message->eventType) {
            'payment.created' => $this->handlePaymentCreated($message->payload),
            'user.registered' => $this->handleUserRegistered($message->payload),
            default => throw new \RuntimeException("Unknown event: {$message->eventType}"),
        };
    }

    private function handlePaymentCreated(array $payload): void
    {
        // Tenant context is set - queries are scoped
        // ...
    }

    private function handleUserRegistered(array $payload): void
    {
        // ...
    }
}
```

#### Step 4: Laravel Queue Integration for Message Broker

```php
<?php

namespace App\Jobs;

use App\Messages\TenantMessage;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Oured\MultiTenant\Tenancy\TenantContext;

class ProcessBrokerMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly TenantMessage $message
    ) {}

    public function handle(TenantContext $context): void
    {
        // Set tenant context
        $tenant = Tenant::findOrFail($this->message->tenantId);
        $context->setTenant($tenant);

        try {
            // Process message
            $this->processMessage();
        } finally {
            $context->clear();
        }
    }

    private function processMessage(): void
    {
        // All queries are tenant-scoped
        match ($this->message->eventType) {
            'payment.processed' => $this->handlePaymentProcessed(),
            default => null,
        };
    }

    private function handlePaymentProcessed(): void
    {
        // ...
    }
}
```

---

### 8. Composite Resolution (Recommended)

The best approach is a **composite resolver** that tries multiple strategies:

```php
<?php

namespace App\Tenancy\Resolvers;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Oured\MultiTenant\Contracts\TenantResolver;

class CompositeTenantResolver implements TenantResolver
{
    private static ?string $forcedTenantId = null;

    public function __construct(
        private readonly ?Request $request = null
    ) {}

    /**
     * Force a specific tenant (for jobs, commands, tests).
     */
    public static function forceTenant(?string $tenantId): void
    {
        self::$forcedTenantId = $tenantId;
    }

    /**
     * Clear forced tenant.
     */
    public static function clearForcedTenant(): void
    {
        self::$forcedTenantId = null;
    }

    public function resolveTenant(): ?Model
    {
        // Priority 1: Forced tenant (jobs, commands, tests)
        if (self::$forcedTenantId) {
            return Tenant::find(self::$forcedTenantId);
        }

        // Priority 2: Running in console (command)
        if ($this->isRunningInConsole()) {
            return $this->resolveFromConsole();
        }

        // Priority 3: HTTP request context
        if ($this->request) {
            return $this->resolveFromRequest();
        }

        return null;
    }

    private function resolveFromRequest(): ?Model
    {
        // Try session/auth first
        $tenant = $this->resolveFromSession();
        if ($tenant) {
            return $tenant;
        }

        // Try header
        $tenant = $this->resolveFromHeader();
        if ($tenant) {
            return $tenant;
        }

        // Try domain/subdomain
        $tenant = $this->resolveFromDomain();
        if ($tenant) {
            return $tenant;
        }

        // Try route parameter (for webhooks)
        $tenant = $this->resolveFromRoute();
        if ($tenant) {
            return $tenant;
        }

        return null;
    }

    private function resolveFromSession(): ?Model
    {
        // Your session logic
        $session = getSession();
        
        if ($session && $session->tenant_id) {
            return Tenant::find($session->tenant_id);
        }

        return null;
    }

    private function resolveFromHeader(): ?Model
    {
        $tenantId = $this->request->header('X-Tenant-ID');
        
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function resolveFromDomain(): ?Model
    {
        $host = $this->request->getHost();
        $subdomain = explode('.', $host)[0] ?? null;
        
        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            return Tenant::where('subdomain', $subdomain)->first();
        }

        return null;
    }

    private function resolveFromRoute(): ?Model
    {
        $tenantId = $this->request->route('tenant_id');
        
        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function resolveFromConsole(): ?Model
    {
        // Console resolution is typically handled by CommandTenantResolver
        // or by forceTenant() before running
        return null;
    }

    private function isRunningInConsole(): bool
    {
        return app()->runningInConsole();
    }
}
```

#### Register Composite Resolver

```php
<?php

namespace App\Providers;

use App\Tenancy\Resolvers\CompositeTenantResolver;
use Illuminate\Support\ServiceProvider;
use Oured\MultiTenant\Contracts\TenantResolver;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TenantResolver::class, function ($app) {
            return new CompositeTenantResolver(
                request: $app->bound('request') ? $app->make('request') : null
            );
        });
    }
}
```

---

## Best Practices

### 1. Always Include Tenant ID in Async Operations

```php
// ✅ Good - tenant ID is captured
ProcessPayment::dispatch($paymentId); // Job captures tenant automatically

// ❌ Bad - tenant context lost
Queue::push(function () use ($paymentId) {
    // No tenant context here!
    $payment = Payment::find($paymentId);
});
```

### 2. Clear Context After Processing

```php
// ✅ Good - always clear in finally block
try {
    $context->setTenant($tenant);
    $this->process();
} finally {
    $context->clear();
}

// ❌ Bad - context may leak
$context->setTenant($tenant);
$this->process();
// If exception thrown, context is never cleared
```

### 3. Validate Tenant Exists

```php
// ✅ Good - validate tenant
$tenant = Tenant::findOrFail($tenantId);
$context->setTenant($tenant);

// ❌ Bad - may set invalid tenant
$context->setTenantId($tenantId); // Don't assume it exists
```

### 4. Use Middleware for HTTP Requests

```php
// ✅ Good - middleware handles resolution
Route::middleware(['tenant'])->group(function () {
    Route::get('/payments', [PaymentController::class, 'index']);
});

// ❌ Bad - manual resolution in every controller
public function index()
{
    $tenant = $this->resolveTenant(); // Repetitive
}
```

### 5. Log Tenant Context

```php
// ✅ Good - include tenant in logs
Log::info('Payment processed', [
    'tenant_id' => $context->getTenantId(),
    'payment_id' => $payment->id,
]);
```

### 6. Handle Missing Tenant Gracefully

```php
// For public routes - allow null tenant
if (! $context->hasTenant()) {
    // Handle public/unauthenticated request
    return $this->handlePublicRequest();
}

// For tenant-required routes - throw exception
if (! $context->hasTenant()) {
    throw new TenantNotFoundException('Tenant context required');
}
```

---

## Testing Strategies

### Setting Tenant in Tests

```php
<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Oured\MultiTenant\Tenancy\TenantContext;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->setTenant($this->tenant);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    public function test_can_create_payment(): void
    {
        // All queries scoped to $this->tenant
        $response = $this->post('/api/payments', [...]);
        
        $response->assertCreated();
    }
}
```

### Testing Jobs

```php
public function test_job_processes_in_tenant_context(): void
{
    $tenant = Tenant::factory()->create();
    $payment = Payment::factory()->for($tenant)->create();

    // Dispatch with tenant
    ProcessPayment::dispatch($payment->id)->forTenant($tenant->id);

    // Assert job ran in correct context
    Queue::assertPushed(ProcessPayment::class, function ($job) use ($tenant) {
        return $job->tenantId === $tenant->id;
    });
}
```

### Testing Commands

```php
public function test_command_runs_for_specific_tenant(): void
{
    $tenant = Tenant::factory()->create();

    $this->artisan('reports:process', ['--tenant' => $tenant->id])
        ->assertSuccessful();
}
```

---

## Summary

| Context | Resolution Strategy |
|---------|-------------------|
| Authenticated HTTP | Session/Auth User |
| Public HTTP | Domain/Subdomain or Header |
| API Requests | Header (X-Tenant-ID) |
| Queued Jobs | Job payload (tenantId property) |
| Artisan Commands | --tenant option |
| Webhooks | URL parameter or payload lookup |
| Message Broker | Message metadata (tenant_id) |
| Scheduled Tasks | Loop through all tenants or specific tenant |
| Tests | Manual setTenant() in setUp() |

The **Composite Resolver** pattern handles all these cases elegantly by trying multiple strategies in priority order.

---

## See Also

- [README.md](./README.md) - Package overview
- [TESTING.md](./TESTING.md) - Testing guide
- [MULTI_TENANT_REFACTORING_DOCUMENTATION.md](./MULTI_TENANT_REFACTORING_DOCUMENTATION.md) - Architecture details


