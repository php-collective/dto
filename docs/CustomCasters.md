# Custom Casters / Transformers

This document explains how to customize value transformation when populating DTOs from arrays and when converting them back to arrays.

## Overview

DTOs support two transformation mechanisms:

| Option | Direction | Purpose |
|--------|-----------|---------|
| `factory` | Array → Object | Create objects from raw values using a factory method |
| `serialize` | Object → Array | Control how objects are converted in `toArray()` |
| `transformFrom` / `transformTo` | Array → Value / Value → Array | Apply a callable before hydration or after serialization |

## Factory - Creating Objects

The `factory` option specifies a static method to call when creating an object from array data.

### Basic Usage

```php
// config/dto.php (PHP Builder)
Field::class('money', \Money\Money::class)->factory('fromArray')

// The factory method is called like: Money::fromArray($value)
```

### Factory Formats

**Method on the type class:**
```
factory: "fromArray"
// Calls: TypeClass::fromArray($value)
```

**Method on a different class:**
```
factory: "App\Factory\MoneyFactory::create"
// Calls: App\Factory\MoneyFactory::create($value)
```

### Configuration Examples

#### PHP (Builder API)

```php
use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;
use PhpCollective\Dto\Config\Schema;

return Schema::create()
    ->dto(Dto::create('Order')->fields(
        // Factory method on the type class
        Field::class('money', \Money\Money::class)->factory('fromArray'),

        // Factory method on a different class
        Field::class('date', \DateTimeImmutable::class)
            ->factory('DateTimeImmutable::createFromFormat'),

        // Custom factory class
        Field::class('price', \App\ValueObject\Price::class)
            ->factory('App\Factory\PriceFactory::create'),
    ))
    ->toArray();
```

#### PHP (Array Syntax)

```php
return [
    'Order' => [
        'fields' => [
            'money' => [
                'type' => '\Money\Money',
                'factory' => 'fromArray',
            ],
            'date' => [
                'type' => '\DateTimeImmutable',
                'factory' => 'DateTimeImmutable::createFromFormat',
            ],
        ],
    ],
];
```

#### XML

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="Order">
        <field name="money" type="\Money\Money" factory="fromArray"/>
        <field name="date" type="\DateTimeImmutable" factory="DateTimeImmutable::createFromFormat"/>
        <field name="price" type="\App\ValueObject\Price" factory="App\Factory\PriceFactory::create"/>
    </dto>
</dtos>
```

#### YAML

```yaml
Order:
  fields:
    money:
      type: '\Money\Money'
      factory: fromArray
    date:
      type: '\DateTimeImmutable'
      factory: 'DateTimeImmutable::createFromFormat'
    price:
      type: '\App\ValueObject\Price'
      factory: 'App\Factory\PriceFactory::create'
```

#### NEON

```neon
Order:
    fields:
        money:
            type: \Money\Money
            factory: fromArray
        date:
            type: \DateTimeImmutable
            factory: DateTimeImmutable::createFromFormat
        price:
            type: \App\ValueObject\Price
            factory: App\Factory\PriceFactory::create
```

## Transforms - Pre/Post Processing

Transforms apply a callable to a field value:

```php
Field::string('email')
    ->transformFrom('App\\Transform\\Email::normalize')
    ->transformTo('App\\Transform\\Email::mask');
```

Transforms run:
- **Before hydration** (`transformFrom`) - after key mapping, before factories/DTO creation.
- **After serialization** (`transformTo`) - after `toArray()`/collection conversion, before key mapping.

Order of operations:

1. `mapFrom` (input key mapping)
2. `transformFrom`
3. `factory` / DTO creation / enum handling
4. `serialize` / DTO `toArray()`
5. `transformTo`
6. `mapTo` (output key mapping)

For collections, transforms are applied to each element.

## Choosing the Right Option

| Use Case | Recommended |
|----------|-------------|
| Create a class instance from raw input | `factory` |
| Convert an object to array/string on output | `serialize` |
| Pre/post-process a scalar or array value | `transformFrom` / `transformTo` |

## Serialize - Converting to Array

The `serialize` option controls how objects are converted when calling `toArray()` on the DTO.

### Serialization Modes

| Mode | Method Called | Use Case |
|------|---------------|----------|
| `array` | `->toArray()` | Object has a toArray() method |
| `string` | `->__toString()` | Object implements Stringable |
| `FromArrayToArray` | `->toArray()` + `::fromArray()` | Full round-trip support |

### Auto-Detection

The library automatically detects serialization mode for classes that:
- Implement `FromArrayToArrayInterface` → uses `FromArrayToArray`
- Implement `JsonSerializable` → no transformation (JSON-safe)
- Have a `toArray()` method → uses `array`

You only need to specify `serialize` when you want to override the auto-detected behavior.

### Configuration Examples

#### PHP (Builder API)

```php
return Schema::create()
    ->dto(Dto::create('Product')->fields(
        // Serialize to array using toArray()
        Field::class('dimensions', \App\ValueObject\Dimensions::class)
            ->serialize('array'),

        // Serialize to string using __toString()
        Field::class('sku', \App\ValueObject\Sku::class)
            ->serialize('string'),

        // Full round-trip with FromArrayToArrayInterface
        Field::class('metadata', \App\ValueObject\Metadata::class)
            ->serialize('FromArrayToArray'),
    ))
    ->toArray();
```

#### PHP (Array Syntax)

```php
return [
    'Product' => [
        'fields' => [
            'dimensions' => [
                'type' => '\App\ValueObject\Dimensions',
                'serialize' => 'array',
            ],
            'sku' => [
                'type' => '\App\ValueObject\Sku',
                'serialize' => 'string',
            ],
        ],
    ],
];
```

#### XML

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="Product">
        <!-- Note: serialize is not in XSD yet, use array config for this -->
        <field name="dimensions" type="\App\ValueObject\Dimensions"/>
    </dto>
</dtos>
```

#### YAML

```yaml
Product:
  fields:
    dimensions:
      type: '\App\ValueObject\Dimensions'
      serialize: array
    sku:
      type: '\App\ValueObject\Sku'
      serialize: string
```

#### NEON

```neon
Product:
    fields:
        dimensions:
            type: \App\ValueObject\Dimensions
            serialize: array
        sku:
            type: \App\ValueObject\Sku
            serialize: string
```

## Combining Factory and Serialize

For full round-trip support, combine both options:

```php
Field::class('money', \App\ValueObject\Money::class)
    ->factory('fromArray')
    ->serialize('array')
```

This ensures:
- **Input**: `Money::fromArray(['amount' => 100, 'currency' => 'USD'])` creates the object
- **Output**: `$money->toArray()` converts it back to `['amount' => 100, 'currency' => 'USD']`

## FromArrayToArrayInterface

For the cleanest round-trip support, implement `FromArrayToArrayInterface`:

```php
use PhpCollective\Dto\Dto\FromArrayToArrayInterface;

class Money implements FromArrayToArrayInterface
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public static function createFromArray(array $array): static
    {
        return new static($array['amount'], $array['currency']);
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
```

Classes implementing this interface are automatically detected - no need to specify `factory` or `serialize`:

```php
// Auto-detected as FromArrayToArray
Field::class('money', \App\ValueObject\Money::class)
```

## Practical Examples

### DateTimeImmutable with Custom Format

```php
// Value object wrapper for consistent date handling
class EventDate
{
    private const FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private DateTimeImmutable $date,
    ) {}

    public static function fromString(string $value): self
    {
        return new self(new DateTimeImmutable($value));
    }

    public function __toString(): string
    {
        return $this->date->format(self::FORMAT);
    }
}

// Configuration
Field::class('eventDate', EventDate::class)
    ->factory('fromString')
    ->serialize('string')
```

### Money Value Object

```php
// Using a third-party money library
Field::class('price', \Money\Money::class)
    ->factory('Money\Parser::parse')

// Or with custom wrapper
Field::class('price', \App\Money::class)
    ->factory('fromCents')
    ->serialize('array')
```

### Enum-like Value Object

```php
class Status implements \Stringable
{
    private function __construct(
        public readonly string $value,
    ) {}

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

// Configuration
Field::class('status', Status::class)
    ->factory('fromString')
    ->serialize('string')
```
