# Contributing to Multi-Tenant Package

> © 2026 OurEdu - Contributing guidelines for the multi-tenant package

Thank you for your interest in contributing to this project! This document provides guidelines and instructions for contributing.

## Code of Conduct

Be respectful and professional in all interactions. We welcome contributors of all backgrounds and experience levels.

## Getting Started

### 1. Fork and Clone

```bash
git clone <your-fork-url>
cd multi-tenant-package
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

## Development Workflow

### Writing Code

1. **Follow PSR-12 Code Style**
   - 4 spaces for indentation
   - Use strict types: `declare(strict_types=1);`
   - Follow Laravel conventions

2. **Add PHPDoc Comments**

   ```php
   /**
    * Brief description.
    *
    * Longer description if needed.
    *
    * @param string $param Description
    * @return bool Description
    */
   public function methodName(string $param): bool
   {
       // ...
   }
   ```

3. **Type Hints**
   - Use complete type hints for all parameters and return types
   - Use nullable types where appropriate: `?Model`

### Writing Tests

1. **Test-Driven Development (TDD)**
   - Write tests first
   - Then implement the feature
   - Ensure all tests pass

2. **Test Naming Convention**
   ```php
   public function testDescribeWhatItTests(): void
   ```

3. **Test Structure (Arrange-Act-Assert)**
   ```php
   public function testFeature(): void
   {
       // Arrange
       $data = ['key' => 'value'];

       // Act
       $result = $this->doSomething($data);

       // Assert
       $this->assertEquals('expected', $result);
   }
   ```

4. **Coverage Requirements**
   - Maintain 80%+ code coverage
   - Test all public methods
   - Test edge cases and error conditions

### Running Tests

```bash
# Basic tests
composer test

# With coverage report
composer test:coverage

# Or using Make
make test
make test-coverage
```

## Commit Guidelines

### Commit Message Format

Use conventional commit format:

```
type(scope): subject

body

footer
```

### Types

- **feat**: A new feature
- **fix**: A bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, missing semicolons, etc.)
- **refactor**: Code refactoring without feature changes
- **test**: Adding or updating tests
- **chore**: Build process, dependencies, configuration
- **ci**: CI/CD related changes
- **perf**: Performance improvements

### Examples

```bash
git commit -m "feat(context): Add tenant context caching"
git commit -m "fix(scope): Correct tenant column resolution"
git commit -m "docs: Update README with testing guide"
git commit -m "test: Add TenantContext test suite"
```

### Commit Best Practices

- One logical change per commit
- Write meaningful commit messages
- Reference issues if applicable: `fix: #123`
- Keep commits focused and atomic

## Pull Request Process

### Before Submitting

1. **Run Tests**
   ```bash
   composer test
   ```

2. **Check Coverage**
   ```bash
   composer test:coverage
   ```

3. **Ensure Code Quality**
   - No warnings or errors
   - Proper type hints
   - PHPDoc comments
   - EditorConfig compliance

4. **Update Documentation**
   - Update README.md if needed
   - Update TESTING.md for test changes
   - Add/update PHPDoc comments

### Creating a PR

1. **PR Title**: Use conventional commit format
   ```
   feat(scope): Brief description
   ```

2. **PR Description**:
   ```markdown
   ## Description
   Brief description of changes

   ## Type of Change
   - [ ] Bug fix (non-breaking)
   - [ ] New feature (non-breaking)
   - [ ] Breaking change
   - [ ] Documentation update

   ## Testing
   Describe how to test the changes

   ## Checklist
   - [ ] Tests pass
   - [ ] Coverage maintained (80%+)
   - [ ] Documentation updated
   - [ ] No breaking changes
   ```

3. **Wait for Review**
   - Address feedback promptly
   - Be open to suggestions
   - Discuss disagreements professionally

## Code Review Checklist

Reviewers will check:

- ✅ Tests are included
- ✅ Code coverage is maintained
- ✅ Code follows PSR-12 style guide
- ✅ Type hints are present
- ✅ PHPDoc comments are accurate
- ✅ No breaking changes (unless intentional)
- ✅ Documentation is updated
- ✅ Commit messages are clear
- ✅ No merge conflicts

## Project Structure

```
multi-tenant-package/
├── src/                      # Source code
│   ├── Contracts/           # Interfaces
│   ├── Middleware/          # HTTP middleware
│   ├── Providers/           # Service providers
│   ├── Tenancy/            # Core tenancy logic
│   └── Traits/             # Model traits
├── tests/                   # Test suite
│   ├── Contracts/          # Contract tests
│   ├── Middleware/         # Middleware tests
│   ├── Providers/          # Provider tests
│   ├── Tenancy/           # Tenancy tests
│   ├── Traits/            # Trait tests
│   ├── TestCase.php       # Base test class
│   └── bootstrap.php      # Test bootstrap
├── config/                 # Configuration files
├── .github/               # GitHub workflows
├── composer.json          # Composer configuration
├── phpunit.xml           # PHPUnit configuration
├── TESTING.md            # Testing guide
├── README.md             # Main documentation
└── Makefile              # Development commands
```

## Adding New Features

### Example: Adding a New Class

1. **Create the class** in `src/YourNamespace/YourClass.php`
2. **Add PHPDoc comments**
3. **Write tests** in `tests/YourNamespace/YourClassTest.php`
4. **Update configuration** if needed
5. **Update documentation** in README.md or TESTING.md
6. **Create focused commits** for each logical step

### Example: Adding Tests

1. **Create test file** following naming convention: `*Test.php`
2. **Extend TestCase**: `class YourTest extends TestCase`
3. **Use descriptive test names**: `testFeatureDoesXWhenYOccurs()`
4. **Follow Arrange-Act-Assert pattern**
5. **Clean up in tearDown()**: Close Mockery, reset state

## Documentation

### Code Comments

- Use PHPDoc for classes and public methods
- Inline comments for complex logic
- Avoid obvious comments

### README Updates

Update README.md when:
- Adding new features
- Changing API
- Adding configuration options
- Updating installation steps

### Test Documentation

Update TESTING.md when:
- Adding new test suites
- Changing test structure
- Adding testing patterns or best practices

## Common Issues

### Tests Not Running

```bash
composer install --dev
./vendor/bin/phpunit --version
```

### Coverage Not Generated

Ensure Xdebug is installed:
```bash
php -m | grep xdebug
```

### Git Conflicts

```bash
git fetch origin
git rebase origin/main
# Resolve conflicts
git add .
git rebase --continue
```

## Questions or Issues?

- Check existing issues
- Review documentation in TESTING.md and README.md
- Ask in pull request comments
- Be clear and specific about the problem

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

## Thank You!

Thank you for contributing to making this package better for everyone! 🎉

---

**Happy Coding!**

© 2026 OurEdu

