# Configuration Builder API

The configuration builder provides a fluent, type-safe way to define DTO schemas with full IDE autocomplete support.

## Quick Start

```php
<?php
// config/dto.php

use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;
use PhpCollective\Dto\Config\Schema;

return Schema::create()
    ->dto(Dto::create('User')->fields(
        Field::int('id')->required(),
        Field::string('email')->required(),
        Field::string('name'),
        Field::bool('active')->default(true),
    ))
    ->dto(Dto::create('Address')->fields(
        Field::string('street'),
        Field::string('city')->required(),
        Field::string('country')->default('USA'),
    ))
    ->toArray();
```

## Why Use the Builder?

The builder API offers several advantages over plain arrays:

| Feature | Array Config | Builder API |
|---------|-------------|-------------|
| IDE Autocomplete | No | Yes |
| Typo Prevention | No | Yes (method names) |
| Type Hints | No | Yes |
| Discoverability | Low | High (see all options via IDE) |

## Field Types

### Scalar Types

```php
Field::string('name')      // string
Field::int('count')        // int
Field::float('price')      // float
Field::bool('active')      // bool
Field::mixed('data')       // mixed
```

### Arrays

```php
Field::array('tags')              // array
Field::array('roles', 'string')   // string[]
Field::array('scores', 'int')     // int[]
```

### DTO References

```php
Field::dto('address', 'Address')    // Reference to AddressDto
Field::dto('author', 'User')        // Reference to UserDto
```

### Collections

```php
// ArrayObject collection with singular add method
Field::collection('items', 'Item')->singular('item')

// Associative collection
Field::collection('users', 'User')
    ->singular('user')
    ->associative()

// Associative with custom key
Field::collection('products', 'Product')
    ->singular('product')
    ->associative('sku')
```

### Classes and Value Objects

```php
Field::class('createdAt', \DateTimeImmutable::class)
Field::class('money', \Money\Money::class)->factory('fromArray')
```

### Enums

```php
Field::enum('status', \App\Enum\OrderStatus::class)
```

### Union Types

```php
Field::union('id', 'int', 'string')              // int|string
Field::union('value', 'int', 'float', 'string')  // int|float|string
Field::union('id', 'int', 'string')->required()  // Required union type

// Or using of() for explicit union strings
Field::of('mixed', 'int|string|null')
```

### Custom Types

```php
Field::of('custom', 'CustomType')
```

## Field Modifiers

### Required Fields

```php
Field::string('email')->required()
```

### Default Values

```php
Field::bool('active')->default(true)
Field::string('status')->default('pending')
Field::int('count')->default(0)
```

### Deprecated Fields

```php
Field::string('oldField')->deprecated('Use newField instead')
```

### Factory Methods

For classes that need custom instantiation:

```php
Field::class('date', \DateTimeImmutable::class)->factory('createFromFormat')
Field::class('money', \Money\Money::class)->factory('Money\Parser::parse')
```

### Serialization

Control how complex objects are serialized in `toArray()`:

```php
// Serialize to array using toArray() method
Field::class('data', MyClass::class)->serialize('array')

// Serialize to string using __toString() method
Field::class('value', Stringable::class)->serialize('string')

// Use FromArrayToArrayInterface for both directions
Field::class('config', ConfigObject::class)->serialize('FromArrayToArray')
```

**Serialization modes:**

| Mode | Method Called | Use When |
|------|---------------|----------|
| `array` | `->toArray()` | Object has toArray() method |
| `string` | `->__toString()` | Object implements Stringable |
| `FromArrayToArray` | `->toArray()` + `::fromArray()` | Object has both methods for round-trip |

**Example with custom class:**

```php
// Your value object
class Money implements \Stringable
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }

    public function __toString(): string
    {
        return $this->amount . ' ' . $this->currency;
    }
}

// Configuration
Field::class('price', Money::class)->serialize('array')
// toArray() returns: ['price' => ['amount' => 1000, 'currency' => 'USD']]

Field::class('priceDisplay', Money::class)->serialize('string')
// toArray() returns: ['priceDisplay' => '1000 USD']
```

### Transforms

Apply a callable to transform values before hydration or after serialization:

```php
Field::string('email')->transformFrom('App\\Transform\\Email::normalize')
Field::string('email')->transformTo('App\\Transform\\Email::mask')
```

For collections, transforms are applied to each element.

### Property Mapping

Map field names between different formats when reading from or writing to arrays:

```php
// Map from different input key
Field::string('emailAddress')->mapFrom('email')

// Map to different output key
Field::string('emailAddress')->mapTo('email_address')

// Both directions
Field::string('emailAddress')->mapFrom('email')->mapTo('email_address')
```

**Use cases:**

| Scenario | Configuration |
|----------|--------------|
| API uses snake_case, DTO uses camelCase | `mapFrom('user_name')` |
| Database column differs from property | `mapFrom('usr_email')` |
| Output to legacy system with different naming | `mapTo('EMAIL_ADDRESS')` |
| Complete bi-directional mapping | `mapFrom('email')->mapTo('email_address')` |

**Example: External API Integration**

```php
// External API returns: {"user_name": "John", "created_at": "2024-01-01"}
// Your DTO uses camelCase internally

Dto::create('ApiUser')->fields(
    Field::string('userName')->mapFrom('user_name'),
    Field::string('createdAt')->mapFrom('created_at')->mapTo('timestamp'),
)

// Usage
$dto = new ApiUserDto(['user_name' => 'John', 'created_at' => '2024-01-01']);
echo $dto->getUserName(); // 'John'

$dto->toArray();
// ['userName' => 'John', 'timestamp' => '2024-01-01']
```

**Array syntax:**

```php
'ApiUser' => [
    'fields' => [
        'userName' => [
            'type' => 'string',
            'mapFrom' => 'user_name',
            'mapTo' => 'username',
        ],
    ],
],
```

Note: This should be used carefully. The more renaming, the harder it is to follow this later on.

## DTO Options

### Basic DTO

```php
Dto::create('User')->fields(
    Field::int('id')->required(),
    Field::string('name'),
)
```

### Immutable DTO

```php
Dto::immutable('Event')->fields(
    Field::int('id')->required(),
    Field::class('occurredAt', \DateTimeImmutable::class)->required(),
)

// Or using the modifier
Dto::create('Event')->asImmutable()->fields(...)
```

### DTO Inheritance

```php
Dto::create('FlyingCar')->extends('Car')->fields(
    Field::int('maxAltitude')->default(1000),
    Field::bool('canHover')->default(false),
)
```

### Deprecated DTOs

```php
Dto::create('OldUser')->deprecated('Use User instead')->fields(...)
```

### Using Traits

Add traits to generated DTO classes:

```php
Dto::create('User')->traits(\App\Dto\Traits\TimestampTrait::class)->fields(
    Field::int('id')->required(),
    Field::string('name'),
)

// Multiple traits
Dto::create('Article')
    ->traits(
        \App\Dto\Traits\TimestampTrait::class,
        \App\Dto\Traits\SoftDeleteTrait::class,
    )
    ->fields(...)
```

The traits must be fully qualified class names starting with `\`.

## Complete Example

```php
<?php

use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;
use PhpCollective\Dto\Config\Schema;

return Schema::create()
    // Simple user DTO
    ->dto(Dto::create('User')->fields(
        Field::int('id')->required(),
        Field::string('email')->required(),
        Field::string('name')->required(),
        Field::string('phone'),
        Field::bool('active')->default(true),
        Field::array('roles', 'string'),
        Field::class('createdAt', \DateTimeImmutable::class),
    ))

    // Address DTO
    ->dto(Dto::create('Address')->fields(
        Field::string('street')->required(),
        Field::string('city')->required(),
        Field::string('country')->required(),
        Field::string('zipCode'),
    ))

    // Order with nested DTOs and collection
    ->dto(Dto::create('Order')->fields(
        Field::int('id')->required(),
        Field::dto('customer', 'User')->required(),
        Field::dto('shippingAddress', 'Address')->required(),
        Field::collection('items', 'OrderItem')->singular('item'),
        Field::float('total')->required(),
        Field::enum('status', \App\Enum\OrderStatus::class)->required(),
    ))

    // Order item with union type for flexible pricing
    ->dto(Dto::create('OrderItem')->fields(
        Field::union('productId', 'int', 'string')->required(),  // Flexible ID
        Field::string('name')->required(),
        Field::int('quantity')->required(),
        Field::union('price', 'int', 'float')->required(),  // Price as int or float
    ))

    // Immutable event
    ->dto(Dto::immutable('OrderPlaced')->fields(
        Field::int('orderId')->required(),
        Field::dto('order', 'Order')->required(),
        Field::class('occurredAt', \DateTimeImmutable::class)->required(),
    ))

    // Extended DTO
    ->dto(Dto::create('PremiumUser')->extends('User')->fields(
        Field::string('membershipLevel')->required(),
        Field::class('memberSince', \DateTimeImmutable::class),
    ))

    ->toArray();
```

## Mixing with Array Syntax

The builder produces the same array format as manual configuration. You can mix both approaches:

```php
<?php

use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;

// Using builder for complex DTOs
$schema = [
    // Simple DTO using array syntax
    'SimpleDto' => [
        'fields' => [
            'name' => 'string',
            'count' => 'int',
        ],
    ],
];

// Add complex DTO using builder
$complexDto = Dto::create('ComplexDto')->fields(
    Field::int('id')->required(),
    Field::collection('items', 'Item')->singular('item')->associative('slug'),
);

$schema[$complexDto->getName()] = $complexDto->toArray();

return $schema;
```

## Output Format

The builder generates the same array structure as manual configuration:

```php
// Builder
Dto::create('User')->fields(
    Field::int('id')->required(),
    Field::string('email')->required(),
    Field::bool('active')->default(true),
)

// Produces
[
    'fields' => [
        'id' => ['type' => 'int', 'required' => true],
        'email' => ['type' => 'string', 'required' => true],
        'active' => ['type' => 'bool', 'defaultValue' => true],
    ],
]
```

Simple fields without modifiers are optimized to just the type string:

```php
Field::string('name')  // Produces: 'string' (not ['type' => 'string'])
```

## Validation Rules

Fields support built-in validation rules that are checked when the DTO is constructed:

```php
Dto::create('User')->fields(
    Field::string('name')->required()->minLength(2)->maxLength(100),
    Field::string('email')->pattern('/^[^@]+@[^@]+\.[^@]+$/'),
    Field::int('age')->min(0)->max(150),
    Field::float('score')->min(0.0)->max(100.0),
)
```

### Available Validation Methods

| Method | Applies To | Description |
|--------|-----------|-------------|
| `minLength(int)` | string | Minimum string length (via `mb_strlen`) |
| `maxLength(int)` | string | Maximum string length (via `mb_strlen`) |
| `min(int\|float)` | int, float | Minimum numeric value |
| `max(int\|float)` | int, float | Maximum numeric value |
| `pattern(string)` | string | Regex pattern (must match via `preg_match`) |

Null fields skip validation — rules are only checked when a value is present.

On failure, an `InvalidArgumentException` is thrown with a descriptive message.

## Lazy Properties

DTO and collection fields can be marked as lazy, deferring hydration until first access:

```php
Dto::create('Order')->fields(
    Field::int('id')->required(),
    Field::dto('customer', 'Customer')->asLazy(),
    Field::collection('items', 'OrderItem')->singular('item')->asLazy(),
)
```

### How It Works

Lazy fields store raw array data during construction in an internal `$_lazyData` property.
When the getter is called for the first time, the raw data is hydrated into the DTO/collection
and cached. Subsequent getter calls return the cached instance.

```php
$order = new OrderDto([
    'id' => 1,
    'customer' => ['name' => 'John', 'email' => 'john@example.com'],
    'items' => [
        ['product' => 'Widget', 'quantity' => 2],
        ['product' => 'Gadget', 'quantity' => 1],
    ],
]);

// No CustomerDto or OrderItemDto objects created yet

$customer = $order->getCustomer();  // Now CustomerDto is hydrated
$items = $order->getItems();        // Now OrderItemDto[] is hydrated
```

### toArray() Behavior

If `toArray()` is called before the getter, the raw data is returned directly — no object
creation occurs. This is useful for pass-through scenarios where DTOs are used for validation
and transport without accessing nested fields:

```php
$order = new OrderDto($apiResponse);
$json = json_encode($order->toArray());  // No nested DTOs created
```

### When to Use Lazy Properties

- **Large nested structures** where not all fields are always accessed
- **API pass-through** where data is validated and forwarded without deep inspection
- **Performance-critical paths** where avoiding unnecessary object creation matters
- **Deep object graphs** where eager hydration would create many unused objects

### Mixing Lazy and Eager Fields

You can mix lazy and eager fields in the same DTO:

```php
Dto::create('Order')->fields(
    Field::int('id')->required(),
    Field::string('status')->required(),           // Eager - always hydrated
    Field::dto('customer', 'Customer')->asLazy(),  // Lazy - on-demand
    Field::dto('summary', 'OrderSummary'),         // Eager - always hydrated
    Field::collection('items', 'OrderItem')->singular('item')->asLazy(),  // Lazy
)
```

## Readonly Properties

DTOs can use PHP's `readonly` modifier for true immutability at the language level:

```php
Dto::create('Config')->readonlyProperties()->fields(
    Field::string('host')->required(),
    Field::int('port')->default(8080),
)
```

This generates `public readonly` properties instead of `protected` ones, providing:

- Direct public property access (`$dto->host` instead of `$dto->getHost()`)
- Compile-time immutability enforcement (assignment after construction throws `\Error`)
- Getters are still generated for consistency

Note: `readonlyProperties()` implies `immutable` — the DTO will extend `AbstractImmutableDto`
and use `with*()` methods (which reconstruct from array) instead of setters.

### Usage Example

```php
$config = new ConfigDto(['host' => 'localhost', 'port' => 3306]);

// Direct property access
echo $config->host;  // "localhost"
echo $config->port;  // 3306

// Getters also work
echo $config->getHost();  // "localhost"

// Attempting to modify throws \Error at runtime
$config->host = 'other';  // Error: Cannot modify readonly property

// Use with*() methods to create modified copies
$newConfig = $config->withPort(5432);
echo $config->port;     // 3306 (original unchanged)
echo $newConfig->port;  // 5432
```

### Readonly vs Immutable: When to Use Which

| Feature | `immutable()` | `readonlyProperties()` |
|---------|---------------|------------------------|
| Property visibility | `protected` | `public readonly` |
| Property access | `$dto->getName()` | `$dto->name` or `$dto->getName()` |
| Modification protection | Convention (no setters) | Language-enforced |
| `with*()` implementation | Clone + set property | Reconstruct from array |
| API consistency | Same as mutable DTOs | Different from mutable DTOs |

**Choose `immutable()`** when:
- You want consistent getter-based API across all DTOs (mutable and immutable)
- You're migrating between mutable and immutable and want minimal code changes

**Choose `readonlyProperties()`** when:
- You prefer shorter syntax with direct property access
- You want IDE/static analysis to catch accidental mutation attempts
