<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that represents your tenant. In your payment backend
    | this could be something like:
    |
    |   Domain\Models\Tenant\Tenant::class
    |
    */
    'tenant_model' => env('MULTI_TENANT_MODEL', 'App\\Models\\Tenant'),

    /*
    |--------------------------------------------------------------------------
    | Default Tenant Column
    |--------------------------------------------------------------------------
    |
    | The default column name used for tenant scoping on models when they
    | don't define a custom getTenantColumn() method.
    |
    */
    'tenant_column' => env('MULTI_TENANT_COLUMN', 'tenant_id'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolver
    |--------------------------------------------------------------------------
    |
    | The class responsible for resolving the current tenant.
    |
    | Default: ChainTenantResolver - Tries multiple resolvers in order:
    |   1. UserSessionTenantResolver - Uses shared UserSession access table
    |   2. DomainTenantResolver      - Uses domain column on Tenant model
    |
    | You can override this with your own implementation by setting the
    | MULTI_TENANT_RESOLVER env variable or binding TenantResolver in
    | your service container.
    |
    */
    'resolver' => env('MULTI_TENANT_RESOLVER', \Ouredu\MultiTenant\Resolvers\ChainTenantResolver::class),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration (Authenticated Routes)
    |--------------------------------------------------------------------------
    |
    | Configuration for UserSessionTenantResolver.
    | This assumes all services share a common UserSession access table that
    | stores tenant_id for authenticated requests.
    |
    */
    'session' => [
        /*
        |--------------------------------------------------------------------------
        | Session Model
        |--------------------------------------------------------------------------
        |
        | The Eloquent model that represents the shared user session/access
        | table used across services.
        |
        | Example: Domain\Models\UserSession\UserSession::class
        |
        */
        'model' => env('MULTI_TENANT_SESSION_MODEL'),

        /*
        |--------------------------------------------------------------------------
        | Session Identifier Column
        |--------------------------------------------------------------------------
        |
        | The column on the session model used to look up a session by the
        | identifier provided on the request (header, etc).
        |
        */
        'id_column' => env('MULTI_TENANT_SESSION_ID_COLUMN', 'id'),

        /*
        |--------------------------------------------------------------------------
        | Session Identifier Header
        |--------------------------------------------------------------------------
        |
        | The HTTP header that carries the session identifier used to look up
        | the shared UserSession record.
        |
        | Example: X-Session-Id
        |
        */
        'id_header' => env('MULTI_TENANT_SESSION_HEADER', 'X-Session-Id'),

        /*
        |--------------------------------------------------------------------------
        | Tenant Column in Session
        |--------------------------------------------------------------------------
        |
        | The column/property name on the session model that stores tenant_id.
        | Defaults back to the global tenant_column if not set.
        |
        */
        'tenant_column' => env('MULTI_TENANT_SESSION_TENANT_COLUMN'),

        /*
        |--------------------------------------------------------------------------
        | Tenant Relationship Name
        |--------------------------------------------------------------------------
        |
        | The relationship method name on the session model that returns the
        | tenant. When this relationship exists (and is optionally eager-loaded
        | via getSession()), the package will use it directly instead of making
        | a separate query to fetch the tenant by ID.
        |
        | This reduces query count when your session model has:
        |   public function tenant(): BelongsTo
        |   {
        |       return $this->belongsTo(Tenant::class, 'tenant_id');
        |   }
        |
        */
        'tenant_relation' => env('MULTI_TENANT_SESSION_TENANT_RELATION', 'tenant'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration (Public Routes)
    |--------------------------------------------------------------------------
    |
    | Configuration for DomainTenantResolver.
    | Resolves tenant from request host/domain when no session is available.
    |
    */
    'domain' => [
        /*
        |--------------------------------------------------------------------------
        | Domain Column
        |--------------------------------------------------------------------------
        |
        | The column name on the tenant model that stores the domain value.
        |
        */
        'column' => env('MULTI_TENANT_DOMAIN_COLUMN', 'domain'),

        /*
        |--------------------------------------------------------------------------
        | Use Subdomain
        |--------------------------------------------------------------------------
        |
        | Whether to extract just the subdomain from the host.
        |
        | If true: 'school1.ouredu.com' → 'school1'
        | If false: uses full host 'school1.ouredu.com'
        |
        */
        'use_subdomain' => env('MULTI_TENANT_USE_SUBDOMAIN', false),

        /*
        |--------------------------------------------------------------------------
        | Base Domain
        |--------------------------------------------------------------------------
        |
        | The base domain to extract subdomain from when use_subdomain is true.
        |
        | Example: 'ouredu.com'
        | Request 'school1.ouredu.com' → subdomain 'school1'
        |
        */
        'base_domain' => env('MULTI_TENANT_BASE_DOMAIN'),
    ],
];
