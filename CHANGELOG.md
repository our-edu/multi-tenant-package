# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- **BREAKING:** `TenantResolver::resolveTenant()` changed to `resolveTenantId()` returning `?int` instead of `?Model`
- **BREAKING:** `TenantContext` now stores `tenant_id` as `int` instead of tenant Model
- **BREAKING:** Removed `getTenant()`, `setTenant()`, `setTenantById()` methods from TenantContext
- **BREAKING:** Removed `runWithTenant()` and `runWithTenantId()` - use `runForTenant(int $tenantId, callable)` instead
- **BREAKING:** `TenantMiddleware` now throws `TenantNotResolvedException` when no tenant is resolved

### Added
- `HeaderTenantResolver` - Gets tenant_id from request header for specific routes
- `UserSessionTenantResolver` - Gets tenant_id from configurable session helper function
- `DomainTenantResolver` - Gets tenant_id by querying tenant table by domain
- `ChainTenantResolver` - Chains multiple resolvers together (default)
- `TenantNotResolvedException` - Thrown when no resolver returns a valid tenant ID
- `TenantQueryListener` - Logs errors when queries run without tenant_id filter
- Excluded routes support in `TenantMiddleware` - bypass tenant resolution for configured routes
- Translatable exception messages via Laravel's translation system
- `setTenantId(?int $tenantId)` method on TenantContext
- `runForTenant(int $tenantId, callable $callback)` method on TenantContext
- Configurable session helper function name via config
- `tenant:migrate` command to add tenant_id column to configured tables
- `tenant:add-trait` command to add HasTenant trait to model classes
- `tenant:add-listener-trait` command to add SetsTenantFromPayload trait to listener classes
- `tables` config option to define tables that need tenant_id column
- `listeners` config option to define listener classes that need SetsTenantFromPayload trait
- `query_listener` config option to enable/disable and set log channel
- `header` config option for HeaderTenantResolver configuration
- `excluded_routes` config option for middleware route exclusion
- Language files for exception messages (`lang/en/exceptions.php`)


## [1.0.0] - 2026-01-12

### Added
- Initial release
- `TenantContext` - Core tenant context management with lazy loading
- `TenantResolver` - Contract for custom tenant resolution strategies
- `TenantScope` - Global Eloquent scope for automatic tenant filtering
- `HasTenant` - Trait for tenant-aware Eloquent models
- `TenantMiddleware` - HTTP middleware for tenant resolution
- Auto-publishing of configuration file
- Support for Laravel 9, 10, and 11
- Support for PHP 8.1, 8.2, and 8.3
- Comprehensive test suite with 31 tests

### Features
- Automatic tenant ID assignment on model creation/update
- Custom tenant column support via `getTenantColumn()` method
- Manual tenant context setting for jobs, commands, and tests

