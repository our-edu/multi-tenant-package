<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam;

/**
 * Why a request's token claims could not be resolved.
 */
enum ClaimsFailure: string
{
    // No Bearer token on the request (jobs, commands, public routes, ?token=).
    case MissingToken = 'missing_token';

    // IAM answered but refused the token, or returned claims without an identity.
    case Rejected = 'rejected';

    // IAM was unreachable, timed out or errored; not the client's fault.
    case Unavailable = 'unavailable';
}
