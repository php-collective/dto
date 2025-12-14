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
