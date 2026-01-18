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
    | The Eloquent model that represents your tenant. Used by DomainTenantResolver
    | to query tenants by domain.
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
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for UserSessionTenantResolver.
    | This resolver uses a helper function to get the session with tenant_id.
    |
    */
    'session' => [
        /*
        |--------------------------------------------------------------------------
        | Session Helper Function
        |--------------------------------------------------------------------------
        |
        | The name of the global helper function that returns the current session
        | object with tenant_id property. This function should return an object
        | that has the tenant_id column/property.
        |
        | Example: 'getSession' will call getSession() to get the session.
        |
        */
        'helper' => env('MULTI_TENANT_SESSION_HELPER', 'getSession'),

        /*
        |--------------------------------------------------------------------------
        | Tenant Column in Session
        |--------------------------------------------------------------------------
        |
        | The column/property name on the session object that stores tenant_id.
        | Defaults to the global tenant_column if not set.
        |
        */
        'tenant_column' => env('MULTI_TENANT_SESSION_TENANT_COLUMN', 'tenant_id'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for DomainTenantResolver.
    | Resolves tenant from request domain.
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Tables
    |--------------------------------------------------------------------------
    |
    | List of tables that require tenant_id column. Used by:
    | - `php artisan tenant:migrate` command to add tenant_id column
    | - Query listener to detect queries without tenant_id filter
    |
    */
    'tables' => [
        // Example:
        // 'users',
        // 'orders',
        // 'invoices',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Listener
    |--------------------------------------------------------------------------
    |
    | Configuration for the database query listener that monitors queries
    | and logs errors when a query is executed on a tenant table without
    | a tenant_id filter.
    |
    */
    'query_listener' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Query Listener
        |--------------------------------------------------------------------------
        |
        | Set to true to enable the query listener. When enabled, the listener
        | will log errors for queries that don't have a tenant_id filter.
        |
        */
        'enabled' => env('MULTI_TENANT_QUERY_LISTENER_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Log Channel
        |--------------------------------------------------------------------------
        |
        | The log channel to use for logging missing tenant filter errors.
        | Leave null to use the default log channel.
        |
        */
        'log_channel' => env('MULTI_TENANT_QUERY_LISTENER_CHANNEL'),
    ],
];
