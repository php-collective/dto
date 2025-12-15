# Shaped Array Types (Proposal)

## Overview

Generate PHPStan/Psalm shaped array annotations for `toArray()` and `createFromArray()` methods, enabling full static analysis of array structures.

## Current State

Currently, `toArray()` returns an untyped array:

```php
/**
 * @return array
 */
public function toArray(): array
```

PHPStan cannot infer what keys exist or their types.

## Proposed Change

Generate shaped array PHPDoc annotations based on DTO field definitions:

```php
/**
 * @return array{name: ?string, count: ?int, active: ?bool}
 */
public function toArray(): array

/**
 * @param array{name?: string, count?: int, active?: bool} $array
 */
public static function createFromArray(array $array): static
```

## Benefits

1. **IDE Autocomplete** - `$dto->toArray()['na` suggests `name`
2. **Typo Detection** - `$dto->toArray()['naem']` shows error
3. **Type Inference** - `['name' => $name] = $dto->toArray()` infers `$name` as `?string`
4. **Destructuring Support** - Full type safety when unpacking arrays

## Implementation Notes

### Type Mapping

| DTO Field Type | Shaped Array Type |
|----------------|-------------------|
| `string` (nullable) | `?string` |
| `int` (required) | `int` |
| `NestedDto` | `array{...}` or `NestedDtoArrayShape` |
| `Item[]` collection | `array<int, array{...}>` |
| Associative collection | `array<string, array{...}>` |

### Nested DTOs

For nested DTOs, two approaches:

**Option A: Inline nested shapes**
```php
/** @return array{owner: array{name: ?string, email: ?string}} */
```

**Option B: Type aliases**
```php
/**
 * @phpstan-type OwnerArrayShape array{name: ?string, email: ?string}
 * @return array{owner: OwnerArrayShape}
 */
```

### Optional vs Required Keys

- Required fields: `array{name: string}` (key always present)
- Optional fields: `array{name?: string}` (key may be absent)

For `toArray()` return type, all keys are always present (use `?type` for nullable).
For `createFromArray()` input, use `key?:` for optional fields.

## Configuration

Could be enabled via generator config:

```php
// config/dto.php
return Schema::create()
    ->config(['shapedArrayTypes' => true])
    // ...
```

Or per-DTO:

```php
Dto::create('User')
    ->shapedArrayTypes(true)
    // ...
```

## Considerations

1. **Long type definitions** - DTOs with many fields create verbose PHPDoc
2. **Nested depth** - Deeply nested DTOs may need type aliases for readability
3. **Collection types** - Collections need special handling for the inner shape
4. **Backward compatibility** - This is additive; existing code unaffected

## Related

- [PHPStan Array Shapes Documentation](https://phpstan.org/writing-php-code/phpdoc-types#array-shapes)
- [Psalm Array Types Documentation](https://psalm.dev/docs/annotating_code/type_syntax/array_types/)
