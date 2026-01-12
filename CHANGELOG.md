# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
- `setTenantById()` helper method
- `runWithTenant()` and `runWithTenantId()` for scoped execution

## [Unreleased]

### Planned
- TenantAwareJob trait for queue jobs
- TenantAwareCommand trait for Artisan commands
- TenantMessage DTO for message broker integration
- SetTenantForJob middleware

