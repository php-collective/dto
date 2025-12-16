# Contributing to php-collective/dto

Thank you for considering contributing to the DTO library! This document provides guidelines and instructions for contributing.

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/php-collective/dto.git
   cd dto
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Verify your setup:
   ```bash
   composer check
   ```

## Requirements

- PHP 8.2 or higher
- Composer

## Code Style

This project follows PSR-12 with the PHPCollective ruleset. Before submitting code:

```bash
# Check for style violations
composer cs-check

# Auto-fix style issues
composer cs-fix
```

## Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Generator/BuilderTest.php

# Run specific test method
vendor/bin/phpunit --filter testBuildWithEnums
```

## Static Analysis

The project uses PHPStan at level 8:

```bash
composer stan
```

## Before Submitting a PR

Run all checks:

```bash
composer check
```

This runs both code style checks and tests. Additionally run:

```bash
composer stan
```

## Pull Request Process

1. Create a feature branch from `master`
2. Make your changes
3. Ensure all checks pass (`composer check` and `composer stan`)
4. Write or update tests for your changes
5. Update documentation if needed (especially README.md and docs/)
6. Submit a pull request to `master`

## Adding New Features

When adding new features:

1. **Add tests** - Create test cases in `tests/` mirroring the source structure
2. **Update documentation** - Add or update docs in `docs/` directory
3. **Consider backwards compatibility** - Avoid breaking changes when possible

## Test Fixtures

Test fixtures for generated DTOs are in `tests/Generator/Fixtures/`. When adding tests that require new DTO classes or enums, add them there.

## Project Structure

```
src/
├── Config/        # Configuration builder classes
├── Dto/           # Base DTO classes (runtime)
├── Engine/        # Config parsers and validators
├── Generator/     # Code generation logic
├── Importer/      # JSON Schema/data importers
└── Utility/       # Helper utilities

tests/
├── Config/        # Config builder tests
├── Dto/           # DTO runtime tests
├── Engine/        # Parser/validator tests
├── Generator/     # Generation tests
│   └── Fixtures/  # Test DTO classes
├── Importer/      # Importer tests
└── Utility/       # Utility tests

docs/              # Documentation files
```

## Reporting Issues

When reporting issues, please include:

- PHP version
- Library version
- Minimal code example to reproduce
- Expected vs actual behavior
- Error messages (if any)

## Questions?

Open an issue on GitHub for questions or discussions.
