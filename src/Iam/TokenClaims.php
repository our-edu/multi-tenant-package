<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Iam;

/**
 * The claims IAM resolved for the current request's token.
 */
final class TokenClaims
{
    public readonly string $user_uuid;

    public readonly string $role_name;

    public readonly ?string $role_uuid;

    public readonly ?string $branch_uuid;

    public readonly bool $check_branch;

    public readonly array $user_branches;

    public readonly ?string $academic_year_uuid;

    public readonly bool $is_valid;

    public readonly bool $is_active;

    public readonly ?int $tenant_id;

    public readonly array $branch_educational_systems;

    public readonly array $user_educational_systems;

    public readonly ?string $timezone;

    public function __construct(private readonly array $data)
    {
        $this->user_uuid = (string) ($data['user_uuid'] ?? '');
        $this->role_name = (string) ($data['role_name'] ?? '');
        $this->role_uuid = $data['role_uuid'] ?? null;
        $this->branch_uuid = $data['branch'] ?? null;
        $this->check_branch = isset($data['branch']) && $data['branch'] !== '*';
        $this->user_branches = $data['user_branches'] ?? [];
        $this->academic_year_uuid = $data['academic_year_uuid'] ?? null;
        $this->is_valid = (bool) ($data['is_valid'] ?? false);
        $this->is_active = (bool) ($data['is_active'] ?? false);
        $this->tenant_id = is_numeric($data['tenant_id'] ?? null) ? (int) $data['tenant_id'] : null;
        $this->branch_educational_systems = $data['branch_educational_systems'] ?? [];
        $this->user_educational_systems = $data['user_educational_systems'] ?? [];
        $this->timezone = $data['timezone'] ?? null;
    }

    /**
     * Any key of the IAM payload, including ones this class does not type yet.
     */
    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
