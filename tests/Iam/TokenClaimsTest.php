<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Tests\Iam;

use Ouredu\MultiTenant\Iam\TokenClaims;
use PHPUnit\Framework\TestCase;

class TokenClaimsTest extends TestCase
{
    public function test_it_maps_the_iam_payload(): void
    {
        $claims = new TokenClaims([
            'user_uuid' => 'user-1',
            'role_name' => 'teacher',
            'role_uuid' => 'role-1',
            'branch' => 'branch-1',
            'user_branches' => ['branch-1'],
            'academic_year_uuid' => 'year-1',
            'is_valid' => 1,
            'is_active' => true,
            'tenant_id' => '7',
            'branch_educational_systems' => ['es-1'],
            'user_educational_systems' => ['es-2'],
            'timezone' => 'Asia/Riyadh',
        ]);

        $this->assertSame('user-1', $claims->user_uuid);
        $this->assertSame('teacher', $claims->role_name);
        $this->assertSame('role-1', $claims->role_uuid);
        $this->assertSame('branch-1', $claims->branch_uuid);
        $this->assertTrue($claims->check_branch);
        $this->assertSame(['branch-1'], $claims->user_branches);
        $this->assertSame('year-1', $claims->academic_year_uuid);
        $this->assertTrue($claims->is_valid);
        $this->assertTrue($claims->is_active);
        $this->assertSame(7, $claims->tenant_id);
        $this->assertSame(['es-1'], $claims->branch_educational_systems);
        $this->assertSame(['es-2'], $claims->user_educational_systems);
        $this->assertSame('Asia/Riyadh', $claims->timezone);
    }

    public function test_optional_fields_default_to_empty(): void
    {
        $claims = new TokenClaims(['user_uuid' => 'user-1', 'role_name' => 'student']);

        $this->assertNull($claims->branch_uuid);
        $this->assertFalse($claims->check_branch);
        $this->assertSame([], $claims->user_branches);
        $this->assertFalse($claims->is_valid);
        $this->assertFalse($claims->is_active);
        $this->assertNull($claims->tenant_id);
        $this->assertSame([], $claims->branch_educational_systems);
        $this->assertSame([], $claims->user_educational_systems);
        $this->assertNull($claims->timezone);
    }

    public function test_a_non_numeric_tenant_id_is_null(): void
    {
        foreach (['abc', '', [], true] as $tenantId) {
            $claims = new TokenClaims(['user_uuid' => 'u', 'role_name' => 'r', 'tenant_id' => $tenantId]);

            $this->assertNull($claims->tenant_id);
        }
    }

    public function test_a_wildcard_branch_does_not_require_a_branch_check(): void
    {
        $claims = new TokenClaims(['user_uuid' => 'u', 'role_name' => 'r', 'branch' => '*']);

        $this->assertFalse($claims->check_branch);
    }

    public function test_raw_reads_untyped_keys(): void
    {
        $claims = new TokenClaims(['user_uuid' => 'u', 'role_name' => 'r', 'new_field' => 'x']);

        $this->assertSame('x', $claims->raw('new_field'));
        $this->assertSame('fallback', $claims->raw('missing', 'fallback'));
        $this->assertSame('x', $claims->toArray()['new_field']);
    }
}
