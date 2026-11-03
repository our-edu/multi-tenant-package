<?php

declare(strict_types=1);

/**
 * Copyright (c) 2026 OurEdu
 * Multi-Tenant Infrastructure for Laravel Services
 */

namespace Ouredu\MultiTenant\Traits;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Throwable;

/**
 * HasUserScope Trait
 *
 * Opt-in role scope (HOD, Section Head, Head of Stage, School Guide). Unlike
 * HasTenant this is NOT a global scope: lists call ->userScope() explicitly,
 * and implicit route model binding applies it so a by-id route returns 404
 * for an out-of-scope record.
 *
 * Claims are read from the session (multi-tenant.session.helper, default
 * getSession()) under the same names as the userScopeMap() keys. Override
 * userScopeClaims() when a service keeps them elsewhere.
 *
 * Usage:
 *   class Classroom extends Model
 *   {
 *       use HasTenant, HasUserScope;
 *
 *       protected function userScopeMap(): array
 *       {
 *           return [
 *               'grades' => 'grade_uuid',
 *               'educational_stages' => 'grade.educational_stage_uuid',
 *           ];
 *       }
 *   }
 *
 *   Classroom::query()->userScope()->paginate();
 *
 * Rules:
 *  - A map value without a dot is a column (whereIn); with a dot, the part
 *    before the last dot is a relation path (whereHas).
 *  - A claim that is null or missing restricts nothing; [] matches nothing.
 *  - Claims combine with AND. Claims missing from the map are not applied.
 *  - A model that defines its own resolveRouteBindingQuery() overrides the
 *    trait's; call ->userScope() there yourself.
 */
trait HasUserScope
{
    /**
     * Where each claim lives on this model: claim => 'column' or 'relation.column'.
     *
     * @return array<string, string>
     */
    abstract protected function userScopeMap(): array;

    /**
     * The current scope claims: claim => list of values, [] (matches nothing) or
     * null (no restriction).
     *
     * By default each claim in userScopeMap() is read from the session object
     * returned by multi-tenant.session.helper (getSession()). Override it when a
     * service keeps the claims somewhere else.
     *
     * @return array<string, mixed>
     */
    protected function userScopeClaims(): array
    {
        $helper = (string) config('multi-tenant.session.helper', 'getSession');

        if (! function_exists($helper)) {
            return [];
        }

        try {
            $session = $helper();
        } catch (Throwable) {
            return [];
        }

        if (! is_object($session)) {
            return [];
        }

        $claims = [];

        foreach (array_keys($this->userScopeMap()) as $claim) {
            $claims[$claim] = $session->{$claim} ?? null;
        }

        return $claims;
    }

    /**
     * Limit the query to the scope claims.
     */
    public function scopeUserScope(Builder $query): Builder
    {
        if (! config('multi-tenant.user_scope.enabled', true)) {
            return $query;
        }

        $claims = $this->userScopeClaims();

        foreach ($this->userScopeMap() as $claim => $path) {
            $values = $this->normalizeUserScopeClaim($claims[$claim] ?? null);

            if ($values === null) {
                continue;
            }

            if ($values === []) {
                return $query->whereRaw('0 = 1');
            }

            if (! Str::contains($path, '.')) {
                $query->whereIn($query->qualifyColumn($path), $values);

                continue;
            }

            $relation = Str::beforeLast($path, '.');
            $column = Str::afterLast($path, '.');

            $query->whereHas($relation, function (Builder $related) use ($column, $values): void {
                $related->whereIn($related->qualifyColumn($column), $values);
            });
        }

        return $query;
    }

    /**
     * Apply the user scope to implicit route model binding.
     *
     * @param  mixed  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return mixed
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (! config('multi-tenant.user_scope.route_binding', true)) {
            return $query;
        }

        return $query->userScope();
    }

    /**
     * null stays null (no restriction); an array, collection or single value becomes a list.
     *
     * @return list<string>|null
     */
    private function normalizeUserScopeClaim(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if (is_string($value) || is_int($value)) {
            return [(string) $value];
        }

        if (! is_array($value)) {
            return null;
        }

        return array_values(array_map('strval', array_filter(
            $value,
            static fn ($item): bool => is_string($item) || is_int($item)
        )));
    }
}
