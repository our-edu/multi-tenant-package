<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Oured\MultiTenant\Middleware;

use Closure;
use Illuminate\Http\Request;
use Oured\MultiTenant\Tenancy\TenantContext;

/**
 * TenantMiddleware
 *
 * Simple middleware that ensures the TenantContext is resolved early
 * in the request lifecycle. The actual resolution logic lives in the
 * TenantResolver implementation bound by the host application.
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        // Trigger lazy resolution
        $context->getTenant();

        return $next($request);
    }
}


