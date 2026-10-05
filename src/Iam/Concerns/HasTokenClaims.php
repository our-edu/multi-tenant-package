<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam\Concerns;

use Ouredu\MultiTenant\Iam\TokenClaims;
use Ouredu\MultiTenant\Tenancy\CurrentSession;

/**
 * For a service's UserSession model: builds it from the request's IAM claims.
 * Override fillExtraFromTokenClaims() to map service-specific attributes.
 */
trait HasTokenClaims
{
    public static function fromTokenClaims(TokenClaims $claims): static
    {
        $session = new static();
        $session->fillFromTokenClaims($claims);
        $session->fillExtraFromTokenClaims($claims);

        return $session;
    }

    protected function fillFromTokenClaims(TokenClaims $claims): void
    {
        $this->branch_uuid = $claims->branch_uuid;
        $this->user_branches = $claims->user_branches;
        $this->check_branch = $claims->check_branch;
        $this->academic_year_uuid = $claims->academic_year_uuid;
        $this->role_uuid = $claims->role_uuid;
        $this->role_name = $claims->role_name;
        $this->role_id = $claims->role_uuid;
        $this->user_uuid = $claims->user_uuid;
        $this->user_id = $claims->user_uuid;
        $this->is_valid = $claims->is_valid;
        $this->tenant_id = $claims->tenant_id;
        $this->branch_educational_systems = $claims->branch_educational_systems;
        $this->timezone = $claims->timezone;

        // Also under the names the package reads (tenant resolver, timezone), which a
        // service may configure; with the defaults these are the attributes above
        $this->{CurrentSession::tenantAttribute()} = $claims->tenant_id;
        $this->{CurrentSession::branchAttribute()} = $claims->branch_uuid;
        $this->{CurrentSession::timezoneAttribute()} = $claims->timezone;
    }

    protected function fillExtraFromTokenClaims(TokenClaims $claims): void
    {
    }
}
