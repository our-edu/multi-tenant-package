<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam\Testing;

use Ouredu\MultiTenant\Iam\ClaimsFailure;
use Ouredu\MultiTenant\Iam\TokenClaims;
use Ouredu\MultiTenant\Iam\TokenClaimsResolver;

/**
 * Resolves to fixed claims, or a fixed failure, without calling IAM.
 */
class FakeTokenClaimsResolver extends TokenClaimsResolver
{
    public function __construct(
        private readonly ?TokenClaims $fakeClaims,
        private readonly ?ClaimsFailure $fakeFailure = null,
    ) {
    }

    protected function fetch(): ?TokenClaims
    {
        $this->failure = $this->fakeFailure;

        return $this->fakeClaims;
    }
}
