# Advanced Type Support

This document covers advanced type features including union types, enums, custom classes, generics, and complex type scenarios.

## Type Overview

| Type Category | Examples | PHP Type Hint |
|---------------|----------|---------------|
| Scalar | `string`, `int`, `float`, `bool` | Native types |
| Special | `mixed`, `array`, `object` | Native types |
| Union | `int\|string`, `int\|float\|string` | Union types (PHP 8.0+) |
| DTO Reference | `User`, `Address` | Generated DTO class |
| Class | `\DateTimeImmutable`, `\Money\Money` | FQCN |
| Enum | `\App\Enum\Status` | Enum class |
| Array | `string[]`, `int[]`, `User[]` | `array` with PHPDoc |
| Collection | `Item[]` with `collection: true` | `\ArrayObject` with PHPDoc |

## Union Types

Union types allow a field to accept multiple types. Supported in PHP 8.0+.

### Configuration

#### PHP Builder

```php
use PhpCollective\Dto\Config\Field;

// Two types
Field::union('id', 'int', 'string')

// Three types
Field::union('value', 'int', 'float', 'string')

// With modifiers
Field::union('id', 'int', 'string')->required()

// Alternative using of()
Field::of('id', 'int|string')
```

#### PHP Array

```php
'id' => [
    'type' => 'int|string',
],
'value' => [
    'type' => 'int|float|string',
    'required' => true,
],
```

#### XML

```xml
<field name="id" type="int|string"/>
<field name="value" type="int|float|string" required="true"/>
```

#### YAML

```yaml
id:
  type: 'int|string'
value:
  type: 'int|float|string'
  required: true
```

#### NEON

```neon
id:
    type: int|string
value:
    type: int|float|string
    required: true
```

### Generated Code

```php
// Property
protected int|string|null $id = null;

// Getter
public function getId(): int|string|null
{
    return $this->id;
}

// Setter
public function setId(int|string|null $id): self
{
    $this->id = $id;
    $this->_touchedFields['id'] = true;
    return $this;
}
```

### Nullable Union Types

Union types become nullable automatically unless `required: true`:

```php
// Not required (default) - nullable
Field::union('id', 'int', 'string')
// Type: int|string|null

// Required - not nullable
Field::union('id', 'int', 'string')->required()
// Type: int|string
```

## Enums

Both backed enums and unit enums are supported.

### Backed Enums (Recommended)

Backed enums have scalar values and are the most common:

```php
// Your enum
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
}
```

#### Configuration

```php
// PHP Builder
Field::enum('status', \App\Enum\OrderStatus::class)

// PHP Array
'status' => [
    'type' => '\App\Enum\OrderStatus',
],
```

```xml
<!-- XML -->
<field name="status" type="\App\Enum\OrderStatus"/>
```

```yaml
# YAML
status:
  type: '\App\Enum\OrderStatus'
```

#### Usage

```php
// Set with enum instance
$order->setStatus(OrderStatus::Pending);

// Set from backing value (auto-converted)
$order = new OrderDto(['status' => 'confirmed']);
$order->getStatus(); // OrderStatus::Confirmed

// toArray() returns backing value
$order->toArray(); // ['status' => 'confirmed']
```

### Unit Enums

Unit enums have no backing value:

```php
enum Priority
{
    case Low;
    case Medium;
    case High;
}
```

#### Usage

```php
// Set with enum instance
$task->setPriority(Priority::High);

// Set from case name (string)
$task = new TaskDto(['priority' => 'High']);
$task->getPriority(); // Priority::High

// toArray() returns case name
$task->toArray(); // ['priority' => 'High']
```

### Int-Backed Enums

```php
enum HttpStatus: int
{
    case Ok = 200;
    case NotFound = 404;
    case ServerError = 500;
}

// Set from int value
$response = new ResponseDto(['status' => 200]);
$response->getStatus(); // HttpStatus::Ok
```

## Custom Classes

Any PHP class can be used as a field type.

### Basic Class Fields

```php
// PHP Builder
Field::class('createdAt', \DateTimeImmutable::class)
Field::class('money', \Money\Money::class)

// PHP Array
'createdAt' => [
    'type' => '\DateTimeImmutable',
],
```

```xml
<!-- XML -->
<field name="createdAt" type="\DateTimeImmutable"/>
```

### With Factory Method

When a class needs custom instantiation:

```php
Field::class('date', \DateTimeImmutable::class)
    ->factory('createFromFormat')

Field::class('money', \Money\Money::class)
    ->factory('fromArray')

// External factory
Field::class('price', \App\ValueObject\Price::class)
    ->factory('App\Factory\PriceFactory::create')
```

### With Serialization

Control how objects are converted in `toArray()`:

```php
// Call toArray() method
Field::class('dimensions', Dimensions::class)
    ->serialize('array')

// Call __toString() method
Field::class('sku', Sku::class)
    ->serialize('string')

// Full round-trip with FromArrayToArrayInterface
Field::class('metadata', Metadata::class)
    ->serialize('FromArrayToArray')
```

### Constructor-Based Creation

If no factory is specified and the class has a single-argument constructor, it will be called automatically:

```php
class Wrapper
{
    public function __construct(public mixed $value) {}
}

// No factory needed - constructor called with the value
Field::class('wrapper', Wrapper::class)
```

## DTO References

Reference other DTOs in your schema:

### Simple Reference

```php
// PHP Builder
Field::dto('address', 'Address')
Field::dto('author', 'User')

// PHP Array
'address' => [
    'type' => 'Address',
],
```

```xml
<!-- XML -->
<field name="address" type="Address"/>
```

The generator automatically:
- Resolves the DTO class name (adds namespace and suffix)
- Handles nested array-to-DTO conversion
- Supports deep cloning

### Nested DTO Conversion

```php
$order = new OrderDto([
    'customer' => [
        'name' => 'John',
        'email' => 'john@example.com',
    ],
]);

// Nested array automatically converted to CustomerDto
$order->getCustomer()->getName(); // 'John'
```

## Array Types

### Typed Arrays

Arrays with element type hints:

```php
// PHP Builder
Field::array('tags', 'string')      // string[]
Field::array('scores', 'int')       // int[]
Field::array('users', 'User')       // User[] (DTO array)

// PHP Array
'tags' => 'string[]',
'scores' => ['type' => 'int[]'],
```

```xml
<!-- XML -->
<field name="tags" type="string[]"/>
<field name="users" type="User[]"/>
```

### Generated PHPDoc

Typed arrays generate generic PHPDoc for static analysis:

```php
/**
 * @var array<int, string>
 */
protected array $tags = [];

/**
 * @return array<int, string>
 */
public function getTags(): array
```

### Untyped Arrays

Plain arrays without element type:

```php
Field::array('data')  // Just 'array'

// PHP Array
'data' => 'array',
```

## Collections

Collections provide `add*()`, `get*()`, `has*()` methods for managing items.

### Basic Collection

```php
// PHP Builder
Field::collection('items', 'Item')->singular('item')

// PHP Array
'items' => [
    'type' => 'Item[]',
    'collection' => true,
    'singular' => 'item',
],
```

```xml
<!-- XML -->
<field name="items" type="Item[]" collection="true" singular="item"/>
```

### Generated Methods

```php
// Add item
$cart->addItem(new ItemDto(['name' => 'Widget']));

// Get all items (returns ArrayObject)
$items = $cart->getItems();

// Check if has items
if ($cart->hasItems()) { ... }
```

### Associative Collections

Keyed access to collection items:

```php
// PHP Builder
Field::collection('settings', 'Setting')
    ->singular('setting')
    ->associative()

// With custom key field
Field::collection('products', 'Product')
    ->singular('product')
    ->associative('sku')
```

```xml
<!-- XML -->
<field name="settings" type="Setting[]" collection="true"
       singular="setting" associative="true"/>
<field name="products" type="Product[]" collection="true"
       singular="product" associative="true" key="sku"/>
```

### Generated Methods (Associative)

```php
// Add with key
$config->addSetting('theme', new SettingDto(['value' => 'dark']));

// Get by key
$theme = $config->getSetting('theme');

// Check by key
if ($config->hasSetting('notifications')) { ... }
```

### Custom Collection Type

Override the default `\ArrayObject`:

```php
// PHP Array
'items' => [
    'type' => 'Item[]',
    'collection' => true,
    'singular' => 'item',
    'collectionType' => '\Doctrine\Common\Collections\ArrayCollection',
],
```

```xml
<!-- XML -->
<field name="items" type="Item[]" collection="true"
       singular="item" collectionType="\Doctrine\Common\Collections\ArrayCollection"/>
```

## PHPDoc Generics

The generator creates generic PHPDoc annotations for static analysis tools (PHPStan, Psalm):

### Array Generics

```php
// Configuration: type="string[]"
// Generated:
/**
 * @var array<int, string>
 */
protected array $tags = [];

// Associative array
/**
 * @var array<string, SettingDto>
 */
protected array $settings = [];
```

### Collection Generics

```php
// Configuration: type="Item[]" collection="true"
// Generated:
/**
 * @var \ArrayObject<int, ItemDto>
 */
protected ?\ArrayObject $items = null;

// Associative collection
/**
 * @var \ArrayObject<string, ProductDto>
 */
protected ?\ArrayObject $products = null;
```

## Complex Type Examples

### E-commerce Order

```php
return Schema::create()
    ->dto(Dto::create('Order')->fields(
        Field::int('id')->required(),
        Field::enum('status', \App\Enum\OrderStatus::class)->required(),
        Field::dto('customer', 'Customer')->required(),
        Field::dto('shippingAddress', 'Address'),
        Field::dto('billingAddress', 'Address'),
        Field::collection('items', 'OrderItem')->singular('item'),
        Field::class('total', \Money\Money::class)
            ->factory('fromArray')
            ->serialize('array'),
        Field::class('createdAt', \DateTimeImmutable::class)->required(),
        Field::class('shippedAt', \DateTimeImmutable::class),
    ))
    ->dto(Dto::create('OrderItem')->fields(
        Field::union('productId', 'int', 'string')->required(),
        Field::string('name')->required(),
        Field::int('quantity')->required()->default(1),
        Field::class('unitPrice', \Money\Money::class)
            ->factory('fromArray')
            ->serialize('array'),
    ))
    ->toArray();
```

### API Response with Metadata

```php
return Schema::create()
    ->dto(Dto::create('ApiResponse')->fields(
        Field::bool('success')->required()->default(true),
        Field::mixed('data'),
        Field::array('errors', 'string'),
        Field::dto('pagination', 'Pagination'),
        Field::array('meta'),  // Untyped for flexibility
    ))
    ->dto(Dto::create('Pagination')->fields(
        Field::int('page')->required()->default(1),
        Field::int('perPage')->required()->default(20),
        Field::int('total')->required(),
        Field::int('totalPages')->required(),
        Field::bool('hasMore')->required(),
    ))
    ->toArray();
```

### Event Sourcing

```php
return Schema::create()
    ->dto(Dto::immutable('DomainEvent')->fields(
        Field::string('eventId')->required(),
        Field::string('aggregateId')->required(),
        Field::int('version')->required(),
        Field::class('occurredAt', \DateTimeImmutable::class)->required(),
        Field::array('payload'),
    ))
    ->dto(Dto::immutable('OrderPlaced')->extends('DomainEvent')->fields(
        Field::dto('order', 'Order')->required(),
    ))
    ->dto(Dto::immutable('OrderShipped')->extends('DomainEvent')->fields(
        Field::string('trackingNumber')->required(),
        Field::string('carrier')->required(),
    ))
    ->toArray();
```

## Type Coercion

The library performs automatic type coercion in certain cases:

| Input Type | Target Type | Behavior |
|------------|-------------|----------|
| `string` | Backed Enum | Calls `Enum::tryFrom($value)` |
| `int` | Backed Enum (int) | Calls `Enum::tryFrom($value)` |
| `string` | Unit Enum | Calls `constant(Enum::$value)` |
| `array` | DTO | Calls `Dto::createFromArray($value)` |
| `array` | Class with `fromArray` | Calls factory method |
| `mixed` | Class with constructor | Calls `new Class($value)` |

## Best Practices

1. **Use specific types** - Avoid `mixed` when a more specific type is possible
2. **Prefer backed enums** - They serialize cleanly and work with databases
3. **Use union types sparingly** - Too many types reduce type safety
4. **Add factories for value objects** - Ensure clean instantiation
5. **Configure serialization** - Ensure `toArray()` produces expected output
6. **Leverage PHPDoc generics** - Let static analysis tools help catch errors
