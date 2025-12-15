# Shaped Array Types

## Overview

Generated DTOs include PHPStan/Psalm shaped array annotations for `toArray()` and `createFromArray()` methods, enabling full static analysis of array structures.

## Architecture

The parent `Dto` class provides protected internal methods (`_toArrayInternal()`, `_createFromArrayInternal()`) that contain the serialization logic. Generated DTOs define public `toArray()` and `createFromArray()` methods with shaped array types that call these internal methods.

This design:
- Avoids LSP (Liskov Substitution Principle) violations
- Provides clean PHPStan-compatible types
- Works correctly with DTO inheritance

## Generated Output

Each DTO gets typed methods calling the internal implementation:

```php
/**
 * @return array{name: string|null, count: int|null, active: bool|null}
 */
public function toArray(?string $type = null, ?array $fields = null, bool $touched = false): array
{
    return $this->_toArrayInternal($type, $fields, $touched);
}

/**
 * @param array{name: string|null, count: int|null, active: bool|null} $data
 */
public static function createFromArray(array $data, bool $ignoreMissing = false, ?string $type = null): static
{
    return static::_createFromArrayInternal($data, $ignoreMissing, $type);
}
```

## Benefits

1. **IDE Autocomplete** - `$dto->toArray()['na` suggests `name`
2. **Typo Detection** - `$dto->toArray()['naem']` shows error
3. **Type Inference** - `['name' => $name] = $dto->toArray()` infers `$name` as `string|null`
4. **Destructuring Support** - Full type safety when unpacking arrays

## Type Mapping

| DTO Field Type | Shaped Array Type |
|----------------|-------------------|
| `string` (nullable) | `string\|null` |
| `int` (required) | `int` |
| Nested DTO | `array{...}` (inline nested shape) |
| `Item[]` collection | `array<int, array{...}>` |
| Associative collection | `array<string, array{...}>` |

## Nested DTOs

Nested DTOs are resolved to their full shaped array type:

```php
// Order DTO with Customer DTO field
/** @return array{id: int|null, customer: array{name: string|null, email: string|null}} */
```

## Collections

Collections include the element's shaped array type:

```php
// Order DTO with Item[] collection
/** @return array{id: int|null, items: array<int, array{name: string|null, price: float|null}>} */
```

## DTO Inheritance

When a DTO extends another DTO, each defines its own `toArray()` with its specific fields. Since there's no method override (just calling the internal method), PHPStan won't complain about type mismatches.

```php
// VehicleDto
/** @return array{brand: string|null, year: int|null} */
public function toArray(...): array

// CarDto extends VehicleDto
/** @return array{doors: int|null} */
public function toArray(...): array
```

## Related

- [PHPStan Array Shapes Documentation](https://phpstan.org/writing-php-code/phpdoc-types#array-shapes)
- [Psalm Array Types Documentation](https://psalm.dev/docs/annotating_code/type_syntax/array_types/)
