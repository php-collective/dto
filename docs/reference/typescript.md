---
title: TypeScript Generation
---

# TypeScript Generation

Generate TypeScript interfaces directly from your DTO configuration files.

## Quick Start

```bash
# Generate TypeScript interfaces (single file)
vendor/bin/dto typescript --config-path=config/ --output=frontend/src/types/

# Generate separate files with dashed naming
vendor/bin/dto typescript --multi-file --file-case=dashed --output=types/
```

## CLI Options

```
TypeScript Options:
  --output=PATH        Path for TypeScript output (default: types/)
  --single-file        Generate all types in one file (default)
  --multi-file         Generate each type in separate file
  --readonly           Make all fields readonly
  --strict-nulls       Use '| null' instead of '?'
  --file-case=CASE     File naming: pascal, dashed, snake (default: pascal)
```

See [CLI Reference](/reference/cli) for the full command overview and shared options.

## Examples

### Single File Output (Default)

```bash
vendor/bin/dto typescript --output=frontend/src/types/
```

Creates `types/dto.ts`:

```typescript
// Auto-generated TypeScript definitions from php-collective/dto
// Do not edit directly - regenerate from DTO configuration

export interface UserDto {
    id: number;
    name: string;
    email: string;
    phone?: string;
    active: boolean;
}

export interface AddressDto {
    street: string;
    city: string;
    country: string;
}

export interface OrderDto {
    id: number;
    customer: UserDto;
    shippingAddress: AddressDto;
    items?: OrderItemDto[];
    total: number;
}
```

### Multi-File Output

```bash
vendor/bin/dto typescript --multi-file --file-case=dashed --output=types/
```

Creates:
- `types/user-dto.ts`
- `types/address-dto.ts`
- `types/order-dto.ts`
- `types/index.ts` (re-exports all)

With proper imports:

```typescript
// types/order-dto.ts
import type { UserDto } from './user-dto';
import type { AddressDto } from './address-dto';
import type { OrderItemDto } from './order-item-dto';

export interface OrderDto {
    id: number;
    customer: UserDto;
    shippingAddress: AddressDto;
    items?: OrderItemDto[];
    total: number;
}
```

### Readonly Interfaces

For immutable DTOs or when you want all fields readonly:

```bash
vendor/bin/dto typescript --readonly --output=types/
```

```typescript
export interface UserDto {
    readonly id: number;
    readonly name: string;
    readonly email: string;
}
```

Note: Immutable DTOs automatically get `readonly` fields regardless of this flag.

### Strict Null Handling

By default, optional fields use `?`:

```typescript
email?: string;  // default
```

With `--strict-nulls`:

```typescript
email: string | null;  // explicit null union
```

### Union Types and Imports

PHP unions are converted to TypeScript unions:

```php
Field::union('result', 'User', 'Address')->required()
```

```typescript
result: UserDto | AddressDto;
```

In multi-file mode, referenced DTOs inside unions get the necessary `import type` statements automatically.

## Type Mapping

| PHP Type | TypeScript Type |
|----------|-----------------|
| `int`, `integer` | `number` |
| `float`, `double` | `number` |
| `string` | `string` |
| `bool`, `boolean` | `boolean` |
| `array` | `any[]` |
| `string[]` | `string[]` |
| `int[]` | `number[]` |
| `mixed` | `unknown` |
| `object` | `Record<string, unknown>` |
| `DateTime` | `string` |
| Other DTO | Interface reference |

## File Naming Options

| Option | Example |
|--------|---------|
| `pascal` (default) | `UserDto.ts` |
| `dashed` | `user-dto.ts` |
| `snake` | `user_dto.ts` |

## Comparison with Other Libraries

| Library | TypeScript Generation | Approach |
|---------|----------------------|----------|
| **php-collective/dto** | Built-in | Generates from config files |
| **spatie/laravel-data** | Built-in | Runtime reflection of PHP classes |
| **spatie/typescript-transformer** | Standalone | Transforms PHP classes |
| **cuyz/valinor** | No | Runtime-only |
| **symfony/serializer** | No | Runtime-only |

### Advantages of Config-Based Generation

1. **No application code execution required** - Generation uses DTO config files instead of reflecting over your app's DTO classes
2. **Single Source of Truth** - Both PHP and TypeScript generated from same config
3. **Full Type Information** - Required fields, defaults, and collections preserved
4. **Framework Agnostic** - Works without Laravel, Symfony, etc.
5. **Build-Time Safety** - Type errors caught before deployment

## CI/CD Integration

Add TypeScript generation to your build process:

```yaml
# GitHub Actions example
- name: Generate TypeScript types
  run: vendor/bin/dto typescript --output=frontend/src/types/

- name: Check TypeScript
  run: cd frontend && npm run type-check
```

## Programmatic Usage

```php
use PhpCollective\Dto\Engine\XmlEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\TypeScriptGenerator;

$config = new ArrayConfig(['namespace' => 'App']);
$engine = new XmlEngine();
$builder = new Builder($engine, $config);
$io = new ConsoleIo();

// Build definitions from config
$definitions = $builder->build('config/', []);

// Generate TypeScript
$tsGenerator = new TypeScriptGenerator($io, [
    'singleFile' => false,
    'fileNameCase' => 'dashed',
    'readonly' => false,
    'strictNulls' => false,
]);

$tsGenerator->generate($definitions, 'frontend/src/types/');
```

### Programmatic Options

`TypeScriptGenerator` supports a few advanced options beyond the CLI flags:

| Option | Default | Description |
|--------|---------|-------------|
| `singleFile` | `true` | Generate `dto.ts` instead of per-type files |
| `fileNameCase` | `'pascal'` | File naming for multi-file mode: `pascal`, `dashed`, `snake` |
| `readonly` | `false` | Mark all fields readonly |
| `strictNulls` | `false` | Emit `| null` instead of optional `?` syntax |
| `exportStyle` | `'interface'` | Emit `interface` or `type` declarations |
| `dateType` | `'string'` | Emit date-like PHP types as `string` or `Date` |
| `suffix` | `'Dto'` | Custom suffix for generated interface names |

The CLI currently exposes `singleFile`, `readonly`, `strictNulls`, and `fileNameCase`. The remaining options are available through programmatic usage.
