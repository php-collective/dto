# CLI Reference

Complete reference for the DTO Generator command-line interface.

## Installation

After installing via Composer, the CLI is available at:

```bash
vendor/bin/dto
```

## Commands

### generate (default)

Generate PHP DTOs from configuration files.

```bash
vendor/bin/dto generate [options]
```

This is the default command, so `vendor/bin/dto` is equivalent to `vendor/bin/dto generate`.

### typescript

Generate TypeScript interfaces from configuration files.

```bash
vendor/bin/dto typescript [options]
```

## Common Options

Options available for all commands:

| Option | Description |
|--------|-------------|
| `--config-path=PATH` | Path to config directory (default: `config/`) |
| `--format=FORMAT` | Config format: `xml`, `yaml`, `neon`, `php` (default: auto-detect) |
| `--verbose`, `-v` | Verbose output with detailed information |
| `--quiet`, `-q` | Minimal output, only errors |
| `--help`, `-h` | Show help message |

## Generate Options

Options specific to PHP DTO generation:

| Option | Description |
|--------|-------------|
| `--src-path=PATH` | Path to src directory (default: `src/`) |
| `--namespace=NS` | Namespace for generated DTOs (default: `App`) |
| `--dry-run` | Show what would be generated without writing files |
| `--force` | Regenerate all DTOs, even if unchanged |
| `--confirm` | Validate PHP syntax of generated files |

## TypeScript Options

Options specific to TypeScript generation:

| Option | Description |
|--------|-------------|
| `--output=PATH` | Path for TypeScript output (default: `types/`) |
| `--single-file` | Generate all types in one file (default) |
| `--multi-file` | Generate each type in separate file |
| `--readonly` | Make all interface fields readonly |
| `--strict-nulls` | Use `\| null` instead of `?` for nullable fields |
| `--file-case=CASE` | File naming: `pascal`, `dashed`, `snake` (default: `pascal`) |

## Examples

### Basic Generation

```bash
# Generate with defaults (config/ -> src/Dto/)
vendor/bin/dto generate

# Same as above (generate is default command)
vendor/bin/dto
```

### Custom Paths

```bash
# Custom config and output paths
vendor/bin/dto generate --config-path=dto/ --src-path=app/

# Custom namespace
vendor/bin/dto generate --namespace=MyApp\\Dto

# All custom
vendor/bin/dto generate \
  --config-path=definitions/ \
  --src-path=lib/ \
  --namespace=Acme\\Data
```

### Preview and Debug

```bash
# See what would be generated without writing
vendor/bin/dto generate --dry-run

# Verbose output for debugging
vendor/bin/dto generate --dry-run --verbose

# Validate generated PHP syntax
vendor/bin/dto generate --confirm
```

### Force Regeneration

```bash
# Regenerate all DTOs, ignoring timestamps
vendor/bin/dto generate --force
```

### TypeScript Generation

```bash
# Generate TypeScript interfaces (single file)
vendor/bin/dto typescript

# Custom output directory
vendor/bin/dto typescript --output=frontend/src/types/

# Separate files with dashed naming
vendor/bin/dto typescript --multi-file --file-case=dashed
# Creates: user-dto.ts, order-dto.ts, etc.

# Readonly interfaces with strict nulls
vendor/bin/dto typescript --readonly --strict-nulls
```

### CI/CD Integration

```bash
# In CI pipeline - fail if DTOs are outdated
vendor/bin/dto generate --dry-run
if [ $? -ne 0 ]; then
  echo "DTOs are not up to date. Run 'vendor/bin/dto generate' locally."
  exit 1
fi

# Or validate syntax after generation
vendor/bin/dto generate --confirm
```

## Configuration File Discovery

The CLI auto-detects configuration files in the following order:

1. **Single file**: `{config-path}/dto.{ext}` where `{ext}` is xml, yaml, neon, or php
2. **Multiple files**: `{config-path}/dto/*.{ext}`

### Format Detection

When `--format` is not specified:

1. Checks file extension
2. For directories, scans for first recognized format
3. Falls back to XML

### Multiple Configuration Files

You can split DTOs across multiple files:

```
config/
└── dto/
    ├── user.xml
    ├── order.xml
    └── product.xml
```

All files in the directory are merged during generation.

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (invalid config, generation failure, etc.) |

## Environment Variables

The CLI respects standard environment variables:

| Variable | Effect |
|----------|--------|
| `NO_COLOR` | Disables colored output |
| `TERM=dumb` | Disables colored output |

## Scripting

### Composer Scripts

Add to `composer.json`:

```json
{
  "scripts": {
    "dto:generate": "dto generate",
    "dto:check": "dto generate --dry-run",
    "dto:typescript": "dto typescript --output=frontend/types/"
  }
}
```

Then run:

```bash
composer dto:generate
composer dto:check
```

### Git Hooks

Pre-commit hook to ensure DTOs are current:

```bash
#!/bin/sh
# .git/hooks/pre-commit

vendor/bin/dto generate --dry-run --quiet
if [ $? -ne 0 ]; then
  echo "Error: DTO configuration has changed."
  echo "Run 'vendor/bin/dto generate' and commit the generated files."
  exit 1
fi
```

### Makefile

```makefile
.PHONY: dto dto-check dto-typescript

dto:
	vendor/bin/dto generate

dto-check:
	vendor/bin/dto generate --dry-run

dto-typescript:
	vendor/bin/dto typescript --output=frontend/src/types/
```
