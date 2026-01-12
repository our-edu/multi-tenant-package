<?php

declare(strict_types=1);

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
];


