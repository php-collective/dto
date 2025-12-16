# Separating Generated Code

Best practices for keeping generated DTOs separate from hand-written source code.

## Why Separate Generated Code?

Separating generated code from your source directory offers several benefits:

- **Clearer code ownership** - Distinguishes auto-generated files from hand-written code
- **Easier code review** - Reviewers know generated files don't need manual inspection
- **Simpler static analysis** - Exclude entire directory from phpcs/phpstan
- **Git history clarity** - Generated file changes are clearly identifiable
- **IDE performance** - Some IDEs handle generated code differently

## Directory Structure

### Default Structure

By default, DTOs are generated in `src/Dto/`:

```
project/
├── config/
│   └── dto.xml
├── src/
│   ├── Dto/           # Generated DTOs mixed with source
│   │   ├── UserDto.php
│   │   └── OrderDto.php
│   ├── Controller/
│   └── Service/
└── composer.json
```

### Recommended Structure

Move generated DTOs to a separate `generated/` directory:

```
project/
├── config/
│   └── dto.xml
├── generated/
│   └── Dto/           # Generated DTOs isolated
│       ├── UserDto.php
│       └── OrderDto.php
├── src/
│   ├── Controller/    # Hand-written code only
│   └── Service/
└── composer.json
```

## Setup

### 1. Generate to Separate Directory

Use the `--src-path` option to output to `generated/`:

```bash
vendor/bin/dto generate --src-path=generated/
```

This creates DTOs in `generated/Dto/` with namespace `App\Dto`.

### 2. Configure Composer Autoloading

Add the generated directory to your `composer.json` autoload configuration:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "App\\Dto\\": "generated/Dto/"
        }
    }
}
```

Then regenerate the autoloader:

```bash
composer dump-autoload
```

### 3. Add Composer Script

Add a convenient script for regeneration:

```json
{
    "scripts": {
        "dto:generate": "dto generate --src-path=generated/",
        "dto:check": "dto generate --src-path=generated/ --dry-run"
    }
}
```

Now use:

```bash
composer dto:generate
```

## Static Analysis Configuration

### PHPStan

Exclude the generated directory from analysis in `phpstan.neon`:

```neon
parameters:
    excludePaths:
        - generated/*
```

### PHPCS

Exclude from code style checks in `phpcs.xml`:

```xml
<ruleset>
    <exclude-pattern>generated/*</exclude-pattern>
</ruleset>
```

### PHP-CS-Fixer

Exclude in `.php-cs-fixer.php`:

```php
return (new PhpCsFixer\Config())
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude('generated')
    );
```

## Git Configuration

### Option A: Commit Generated Files (Recommended)

Track generated files in version control for faster deployments:

```gitignore
# .gitignore
# Don't ignore generated/ - we want to track these files
```

**Benefits:**
- No generation step needed during deployment
- Changes are visible in pull requests
- Works with read-only production filesystems

### Option B: Ignore Generated Files

If you prefer runtime generation:

```gitignore
# .gitignore
/generated/
```

Add generation to your deployment script:

```bash
composer install
composer dto:generate
```

## Custom Namespace

If you prefer a different namespace, adjust both CLI and Composer:

```bash
# Generate with custom namespace
vendor/bin/dto generate --src-path=generated/ --namespace=Acme\\Transfer
```

```json
{
    "autoload": {
        "psr-4": {
            "Acme\\Transfer\\": "generated/Dto/"
        }
    }
}
```

## Multiple Applications

For monorepos or multi-module projects:

```
project/
├── modules/
│   ├── billing/
│   │   ├── config/dto.xml
│   │   └── generated/Dto/
│   └── shipping/
│       ├── config/dto.xml
│       └── generated/Dto/
└── composer.json
```

Generate each module separately:

```bash
vendor/bin/dto generate \
  --config-path=modules/billing/config/ \
  --src-path=modules/billing/generated/ \
  --namespace=Billing

vendor/bin/dto generate \
  --config-path=modules/shipping/config/ \
  --src-path=modules/shipping/generated/ \
  --namespace=Shipping
```

Composer autoload:

```json
{
    "autoload": {
        "psr-4": {
            "Billing\\Dto\\": "modules/billing/generated/Dto/",
            "Shipping\\Dto\\": "modules/shipping/generated/Dto/"
        }
    }
}
```

## TypeScript and JSON Schema

The same pattern applies to other generators:

```bash
# TypeScript to separate directory
vendor/bin/dto typescript --output=generated/types/

# JSON Schema to separate directory
vendor/bin/dto jsonschema --output=generated/schemas/
```

Project structure:

```
project/
├── config/
│   └── dto.xml
├── generated/
│   ├── Dto/           # PHP DTOs
│   ├── types/         # TypeScript interfaces
│   └── schemas/       # JSON Schema files
└── src/
```

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/ci.yml
jobs:
  check-dtos:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - run: composer install

      # Verify DTOs are up to date
      - run: composer dto:check

      # Or regenerate and check for diff
      - run: |
          composer dto:generate
          git diff --exit-code generated/
```

### Pre-commit Hook

```bash
#!/bin/sh
# .git/hooks/pre-commit

# Check if DTO config changed
if git diff --cached --name-only | grep -q "config/dto"; then
    vendor/bin/dto generate --src-path=generated/ --dry-run --quiet
    if [ $? -ne 0 ]; then
        echo "DTO configuration changed. Run 'composer dto:generate' and commit the generated files."
        exit 1
    fi
fi
```

## Complete Example

Here's a complete `composer.json` setup:

```json
{
    "name": "acme/my-project",
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "App\\Dto\\": "generated/Dto/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Test\\": "tests/"
        }
    },
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "php-collective/dto": "^1.0",
        "phpstan/phpstan": "^1.0",
        "squizlabs/php_codesniffer": "^3.0"
    },
    "scripts": {
        "dto:generate": "dto generate --src-path=generated/",
        "dto:check": "dto generate --src-path=generated/ --dry-run",
        "dto:typescript": "dto typescript --output=generated/types/",
        "dto:schema": "dto jsonschema --output=generated/schemas/",
        "test": "phpunit",
        "analyse": "phpstan analyse src/",
        "cs": "phpcs src/"
    }
}
```
