---
title: Runtime API
---

# Runtime API

This guide collects the core runtime methods exposed by generated DTOs and the shared base `Dto` class.

Most day-to-day usage happens through generated getters, setters, and `with*()` methods, but the base API is useful when you need dynamic access, key-type conversion, or framework-level helpers.

## Construction

### Constructor

```php
$dto = new UserDto($data, ignoreMissing: false, type: null);
```

- `data`: initial array payload
- `ignoreMissing`: ignore unknown input keys instead of throwing
- `type`: input key style such as underscored or dashed

### `create()`

Convenience wrapper around `new`:

```php
$dto = UserDto::create(['name' => 'Jane']);
```

### `createFromArray()`

Generated DTOs expose a typed static constructor:

```php
$dto = UserDto::createFromArray([
    'id' => 1,
    'email' => 'jane@example.com',
]);
```

This is usually the most readable entry point when you want shaped-array type information in static analysis.

### `fromArray()`

Mutable DTOs also expose an instance-level hydrator:

```php
$dto = new UserDto();
$dto->fromArray(['name' => 'Jane']);
```

This mutates the current instance. Immutable DTOs use `create()`, `createFromArray()`, or generated `with*()` methods instead.

### `fromUnserialized()`

Create a DTO from a JSON string produced by `serialize()`:

```php
$dto = UserDto::fromUnserialized($json);
```

Mutable DTOs also expose an instance-level `unserialize()` method when you want to rehydrate an existing object in place.

## Reading Data

### Generated Getters

Generated DTOs expose typed getters such as:

```php
$dto->getName();
$dto->getAddress();
$dto->getAddressOrFail();
```

### Dynamic `get()` and `has()`

Use these when the field name is only known at runtime:

```php
$value = $dto->get('name');
$present = $dto->has('name');
```

Both methods also accept an optional key type.

`has()` checks whether the field currently has a value, not whether the field exists in the DTO definition.

### `read()`

Safely traverse nested DTOs, arrays, and `ArrayAccess`-backed structures:

```php
$city = $order->read(['customer', 'address', 'city']);
$firstEmail = $company->read(['departments', 0, 'members', 0, 'email'], 'unknown');
```

Path segments can be field names or collection indexes. When any segment is missing, `read()` returns the provided default value.

### `fields()` and `touchedFields()`

```php
$allFields = $dto->fields();
$changedFields = $dto->touchedFields();
```

- `fields()` returns all DTO field names
- `touchedFields()` returns only fields that were set or mutated

### `validationRules()`

Returns built-in validation metadata in a framework-agnostic format:

```php
$rules = $dto->validationRules();
// ['email' => ['pattern' => '/.../'], 'age' => ['min' => 0]]
```

## Serialization

### `toArray()`

Convert the DTO to an array:

```php
$data = $dto->toArray();
$snake = $dto->toArray(UserDto::TYPE_UNDERSCORED);
$subset = $dto->toArray(fields: ['id', 'email']);
```

The optional arguments are:

- `type`: output key style
- `fields`: only serialize a subset of fields
- `touched`: internal flag used by `touchedToArray()`

### `touchedToArray()`

Serialize only fields that were touched:

```php
$changes = $dto->touchedToArray();
```

This is useful for PATCH payloads, change tracking, and emitting only modified state.

### `serialize()` and `__toString()`

`serialize()` returns a JSON string of touched fields:

```php
$json = $dto->serialize();
echo $dto; // same touched-field JSON representation
```

This is different from PHP's native `serialize($dto)`.

### Native `serialize()` / `unserialize()`

DTOs implement `__serialize()` and `__unserialize()` for PHP's native serialization:

```php
$serialized = serialize($dto);
$restored = unserialize($serialized);
```

Native serialization also works on touched fields, not the full DTO state.

### `jsonSerialize()`

DTOs implement `JsonSerializable`, so `json_encode($dto)` uses `toArray()`.

## Mutation

### Mutable DTOs

Mutable DTOs support generated setters and dynamic `set()`:

```php
$dto->setName('Jane');
$dto->set('name', 'Jane');
```

### Immutable DTOs

Immutable DTOs support generated `with*()` methods and dynamic `with()`:

```php
$updated = $dto->withEmail('new@example.com');
$updated = $dto->with('email', 'new@example.com');
```

These return a new instance.

### `clone()`

Creates a deep clone of the DTO, including nested DTOs, arrays, and collections:

```php
$copy = $dto->clone();
```

Lazy field payloads are preserved in the clone as well.

## Key Types

The base `Dto` class supports multiple key styles for input and output:

- `TYPE_DEFAULT`
- `TYPE_CAMEL`
- `TYPE_UNDERSCORED`
- `TYPE_DASHED`

Examples:

```php
$dto->fromArray($request->getData(), false, UserDto::TYPE_UNDERSCORED);
$query = $dto->toArray(UserDto::TYPE_DASHED);
```

### Global Default Key Type

You can set a global default for all DTOs:

```php
use PhpCollective\Dto\Dto\Dto;

Dto::setDefaultKeyType(Dto::TYPE_UNDERSCORED);
```

This affects calls where no explicit key type is passed.

## Collections

### `setCollectionFactory()`

Override collection instantiation globally:

```php
use PhpCollective\Dto\Dto\Dto;

Dto::setCollectionFactory(fn (array $items) => collect($items));
```

This is useful for framework-native collection classes in Laravel, CakePHP, or Symfony integrations.

### Resetting Global Runtime State

Because collection factories and default key types are static global settings, tests should reset them explicitly:

```php
Dto::setCollectionFactory(null);
Dto::setDefaultKeyType(null);
```

## Runtime Exceptions Worth Knowing

Common runtime failures include:

- missing required fields
- unknown dynamic field access in `get()`, `set()`, or `has()`
- invalid regex patterns in built-in validation rules
- incompatible factory return types
- unknown fields during native `unserialize()`

See [Troubleshooting](./troubleshooting) for the full exception guide.
