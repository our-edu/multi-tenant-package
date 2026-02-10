# Testing Guide - Multi-Tenant Package

> © 2026 OurEdu - Testing documentation for the multi-tenant package

## Table of Contents

1. [Overview](#overview)
2. [Running Tests](#running-tests)
3. [Test Structure](#test-structure)
4. [Writing Tests](#writing-tests)
5. [Test Suites](#test-suites)
6. [Code Coverage](#code-coverage)
7. [Best Practices](#best-practices)

---

## Overview

This package includes a comprehensive test suite using **PHPUnit** and **Mockery** for mocking Laravel services.

### Test Dependencies

- **PHPUnit 10.0+** - Testing framework
- **Mockery 1.5+** - Mocking library
- **Orchestra Testbench 8.0+|9.0+** - Testing utilities for Laravel packages

---

## Running Tests

### Basic Test Execution

```bash
composer test
```

### Run with Coverage Report

```bash
composer test:coverage
```

This generates an HTML coverage report in the `coverage/` directory.

### Run Specific Test Suite

```bash
./vendor/bin/phpunit tests/Tenancy/
```

### Run Single Test File

```bash
./vendor/bin/phpunit tests/Tenancy/TenantContextTest.php
```

### Run Single Test Method

```bash
./vendor/bin/phpunit tests/Tenancy/TenantContextTest.php::testGetTenantReturnsNullWhenNotResolved
```

---

## Test Structure

```
tests/
├── .gitignore                          # Test artifacts to ignore
├── bootstrap.php                       # Test bootstrap configuration
├── TestCase.php                        # Base test case class
├── Commands/
│   ├── TenantAddListenerTraitCommandTest.php  # Listener trait command tests
│   ├── TenantAddTraitCommandTest.php   # Model trait command tests
│   └── TenantMigrateCommandTest.php    # Migration command tests
├── Tenancy/
│   ├── TenantContextTest.php          # TenantContext tests
│   └── TenantScopeTest.php            # TenantScope tests
├── Traits/
│   ├── HasTenantTest.php              # HasTenant trait tests
│   └── SetsTenantFromPayloadTest.php  # SetsTenantFromPayload trait tests
├── Middleware/
│   └── TenantMiddlewareTest.php       # TenantMiddleware tests
├── Resolvers/
│   ├── ChainTenantResolverTest.php    # ChainTenantResolver tests
│   ├── DomainTenantResolverTest.php   # DomainTenantResolver tests
│   ├── HeaderTenantResolverTest.php   # HeaderTenantResolver tests
│   └── UserSessionTenantResolverTest.php # UserSessionTenantResolver tests
├── Contracts/
│   └── TenantResolverTest.php         # TenantResolver contract tests
├── Listeners/
│   └── TenantQueryListenerTest.php    # TenantQueryListener tests
├── Exceptions/
│   └── TenantNotResolvedExceptionTest.php # Exception tests
└── Providers/
    └── TenantServiceProviderTest.php  # TenantServiceProvider tests
```

---

## Writing Tests

### Test Case Template

```php
<?php

declare(strict_types=1);

namespace Tests\YourNamespace;

use Tests\TestCase;

class YourTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup test fixtures
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Cleanup
    }

    public function testSomeFeature(): void
    {
        // Arrange
        $expected = 'value';

        // Act
        $result = $this->doSomething();

        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

### Using Mockery

```php
use Mockery;

public function testWithMock(): void
{
    $mock = Mockery::mock(SomeClass::class);
    $mock->shouldReceive('method')
        ->once()
        ->andReturn('result');

    $result = $mock->method();

    $this->assertEquals('result', $result);
}
```

---

## Test Suites

### 1. TenantContext Tests (`tests/Tenancy/TenantContextTest.php`)

Tests the core tenant context service:

- **testGetTenantReturnsNullWhenNotResolved** - Context returns null when no tenant
- **testGetTenantReturnsTenantModel** - Returns the resolved tenant model
- **testGetTenantIdReturnsUuidWhenAvailable** - Uses UUID if available
- **testGetTenantIdReturnsPrimaryKeyWhenUuidNotAvailable** - Falls back to primary key
- **testSetTenantManually** - Allows manual tenant assignment
- **testHasTenantReturnsFalseWhenNoTenant** - Correctly identifies absence
- **testClearTenantContext** - Clears the cached tenant
- **testLazyLoadingTenantResolver** - Caches resolved tenant

### 2. TenantScope Tests (`tests/Tenancy/TenantScopeTest.php`)

Tests the global tenant scope:

- **testApplyScopeAddsWhereClauseWhenTenantExists** - Adds WHERE clause to queries
- **testApplyScopeDoesNothingWhenNoTenant** - Skips scope when no tenant
- **testApplyScopeUsesCustomTenantColumn** - Respects custom column names
- **testForTenantMethod** - Allows explicit tenant filtering

### 3. HasTenant Trait Tests (`tests/Traits/HasTenantTest.php`)

Tests the model trait:

- **testTenantRelationship** - Defines tenant relationship
- **testResolveTenantColumnDefaultsToTenantId** - Uses `tenant_id` by default
- **testResolveTenantColumnUsesCustomMethod** - Respects custom `getTenantColumn()` method
- **testScopeForTenant** - Query scope for filtering by tenant

### 4. TenantMiddleware Tests (`tests/Middleware/TenantMiddlewareTest.php`)

Tests the middleware:

- **testMiddlewareResolvesTenantonHandle** - Triggers tenant resolution
- **testMiddlewareCallsNextMiddleware** - Properly chains middleware
- **testMiddlewareLazyLoadsTenant** - Ensures lazy loading works

### 5. TenantResolver Contract Tests (`tests/Contracts/TenantResolverTest.php`)

Tests the resolver interface:

- **testTenantResolverIsInterface** - Verifies interface exists
- **testTenantResolverCanBeImplemented** - Can be implemented
- **testTenantResolverReturnsNullForNoTenant** - Returns null when appropriate
- **testTenantResolverReturnsModel** - Returns model correctly

### 6. TenantServiceProvider Tests (`tests/Providers/TenantServiceProviderTest.php`)

Tests the service provider:

- **testProviderRegistersTenantContext** - Registers TenantContext
- **testProviderRegistersTenantContextAsSingleton** - Registers as singleton
- **testProviderMergesConfig** - Merges package configuration

---

## Code Coverage

Generate HTML coverage report:

```bash
composer test:coverage
```

View the report:

```bash
open coverage/index.html  # macOS
xdg-open coverage/index.html  # Linux
start coverage/index.html  # Windows
```

### Coverage Goals

- **Line Coverage**: Aim for 80%+
- **Method Coverage**: Aim for 80%+
- **Class Coverage**: Aim for 100%

---

## Best Practices

### 1. Use Descriptive Test Names

```php
// Good
public function testTenantContextReturnsNullWhenNoTenantIsResolved(): void

// Bad
public function testContext(): void
```

### 2. Follow Arrange-Act-Assert Pattern

```php
public function testFeature(): void
{
    // Arrange: Setup
    $data = ['key' => 'value'];

    // Act: Execute
    $result = $this->processData($data);

    // Assert: Verify
    $this->assertEquals('expected', $result);
}
```

### 3. Use Meaningful Assertions

```php
// Good
$this->assertTrue($context->hasTenant());
$this->assertSame($expected, $actual);
$this->assertInstanceOf(Model::class, $result);

// Bad
$this->assertEquals(true, $context->hasTenant());
$this->assertTrue($expected === $actual);
```

### 4. Clean Up After Tests

```php
protected function tearDown(): void
{
    parent::tearDown();
    Mockery::close();  // Close all Mockery instances
}
```

### 5. Test Edge Cases

```php
public function testGetTenantIdWithVariousInputs(): void
{
    // Test with integer ID
    // Test with null
    // Test with empty string
}
```

### 6. Use Constants for Test Data

```php
private const TEST_TENANT_ID = '1';

public function testWithTestData(): void
{
    $tenantId = $this->context->getTenantId();
    $this->assertEquals(self::TEST_TENANT_ID, $tenantId);
}
```

---

## Continuous Integration

The test suite is designed to run in CI/CD pipelines. Example GitHub Actions:

```yaml
- name: Run Tests
  run: composer test

- name: Generate Coverage
  run: composer test:coverage

- name: Upload Coverage
  uses: codecov/codecov-action@v3
  with:
    files: ./coverage/coverage.xml
```

---

## Troubleshooting

### PHPUnit Not Found

```bash
composer install --dev
```

### Tests Not Running

Ensure the bootstrap file is configured in `phpunit.xml`:

```xml
<phpunit bootstrap="tests/bootstrap.php">
```

### Mockery Issues

Close mockery after each test:

```php
protected function tearDown(): void
{
    Mockery::close();
    parent::tearDown();
}
```

### Coverage Not Generated

Ensure Xdebug or PCOV is installed:

```bash
php -m | grep xdebug
```

---

## Contributing Tests

When adding new features:

1. Write tests first (TDD)
2. Ensure all tests pass: `composer test`
3. Maintain coverage above 80%
4. Follow the established test patterns
5. Document complex test scenarios

---

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Mockery Documentation](https://docs.mockery.io/)
- [Testing Laravel Documentation](https://laravel.com/docs/testing)


