---
title: Custom Casters / Transformers
---

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

```ini
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

```ini
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

## Global Type-Based Transformers

The options above (`factory`, `serialize`) are declared **per field** in your DTO schema.
When the same value object type appears in dozens of fields across many DTOs, repeating
the factory on every field becomes tedious. The `TransformerRegistry` lets you register
a caster or serializer **once per type** and have it apply automatically to every field
whose declared type matches.

### Registering Casters and Serializers

```php
use PhpCollective\Dto\Transformer\TransformerRegistry;

// Array -> Object (used during fromArray / createFromArray / new MyDto($data))
TransformerRegistry::addCaster(
    \DateTimeImmutable::class,
    fn (string $value): \DateTimeImmutable => new \DateTimeImmutable($value),
);

// Object -> scalar/array (used during toArray / touchedToArray / jsonSerialize)
TransformerRegistry::addSerializer(
    \DateTimeInterface::class,
    fn (\DateTimeInterface $value): string => $value->format(DATE_ATOM),
);
```

Register them once during application bootstrap. Every DTO field declared as
`\DateTimeImmutable` (or any subclass/implementor of `\DateTimeInterface` on output)
will now be transformed automatically — no per-field `factory` or `serialize` needed.

### Precedence Rules

The registry participates in a clear priority chain:

**`fromArray` (array → value):**

1. Nested DTO handling
2. Collection handling
3. `serialize: 'FromArrayToArray'` / `serialize: 'array'` per field
4. **Per-field `factory`** (wins over registry)
5. Enum handling
6. **`TransformerRegistry::findCaster()`** ← new
7. Default constructor fallback (`new $type($value)`)

**`toArray` (value → array):**

1. Nested DTO `->toArray()`
2. Collection `->toArray()`
3. **Per-field `serialize`** (wins over registry)
4. Unit enum handling
5. **`TransformerRegistry::findSerializer()`** ← new
6. Value passed through as-is

This means explicit schema configuration always overrides global registry entries.

### Inheritance Matching

Lookups try an exact class-name match first, then walk registered types to find a parent
class or interface match:

```php
TransformerRegistry::addSerializer(
    \DateTimeInterface::class,
    fn (\DateTimeInterface $value): string => $value->format(DATE_ATOM),
);

// Both \DateTime and \DateTimeImmutable are serialized by the entry above.
```

Register the most specific type you care about; interface-level registrations are a
convenient catch-all.

### Framework Integration Examples

**CakePHP** (in `Application::bootstrap()`):

```php
use PhpCollective\Dto\Transformer\TransformerRegistry;
use Cake\I18n\DateTime as CakeDateTime;

TransformerRegistry::addCaster(
    CakeDateTime::class,
    fn (mixed $value): CakeDateTime => new CakeDateTime($value),
);
TransformerRegistry::addSerializer(
    CakeDateTime::class,
    fn (CakeDateTime $value): string => $value->toIso8601String(),
);
```

**Laravel** (in `AppServiceProvider::boot()`):

```php
use PhpCollective\Dto\Transformer\TransformerRegistry;
use Illuminate\Support\Carbon;

TransformerRegistry::addCaster(
    Carbon::class,
    fn (mixed $value): Carbon => Carbon::parse($value),
);
TransformerRegistry::addSerializer(
    Carbon::class,
    fn (Carbon $value): string => $value->toIso8601String(),
);
```

### Testing

In tests, clear the registry between cases to avoid leakage across tests:

```php
protected function tearDown(): void
{
    parent::tearDown();
    TransformerRegistry::clear();
}
```

### API Reference

| Method | Description |
|--------|-------------|
| `addCaster(string $type, callable $caster)` | Register a caster for a class/interface. |
| `addSerializer(string $type, callable $serializer)` | Register a serializer for a class/interface. |
| `removeCaster(string $type)` | Remove a registered caster. |
| `removeSerializer(string $type)` | Remove a registered serializer. |
| `hasCaster(string $type): bool` | Check if a caster is registered (exact match). |
| `hasSerializer(string $type): bool` | Check if a serializer is registered (exact match). |
| `findCaster(string $type): ?callable` | Look up a caster (exact then inheritance). |
| `findSerializer(object $value): ?callable` | Look up a serializer for an instance. |
| `hasAny(): bool` | Whether any entry is registered. |
| `clear()` | Remove all entries. |

### When to Use What

| Need | Use |
|------|-----|
| Transform a single one-off field | Per-field `factory` / `serialize` in the schema |
| Transform the same type across many fields / DTOs | `TransformerRegistry` |
| Override registry behaviour for a specific field | Per-field `factory` / `serialize` (wins over registry) |
| Roundtrip a custom value object | Implement `FromArrayToArrayInterface` |

::: tip Performance
When any entry is registered, generated DTOs fall back from the optimized fast path
to the reflective code path to ensure the registry is consulted. If you rely on the
`HAS_FAST_PATH` optimization, prefer per-field `factory`/`serialize` over the registry
for hot-path DTOs.
:::
