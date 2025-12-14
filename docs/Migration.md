# Migration Guide

How to migrate from other DTO libraries to php-collective/dto.

## From spatie/data-transfer-object

**Note:** spatie/data-transfer-object is deprecated since 2023. Spatie recommends migrating to `spatie/laravel-data` or `cuyz/valinor`.

### Before (spatie/data-transfer-object)

```php
use Spatie\DataTransferObject\DataTransferObject;

class UserDto extends DataTransferObject
{
    public int $id;
    public string $name;
    public ?string $email = null;
    public array $roles = [];
}

// Usage
$dto = new UserDto([
    'id' => 1,
    'name' => 'John',
]);
```

### After (php-collective/dto)

**Step 1: Create configuration**

```xml
<!-- config/dto.xml -->
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int" required="true"/>
        <field name="name" type="string" required="true"/>
        <field name="email" type="string"/>
        <field name="roles" type="string[]" collection="true" singular="role"/>
    </dto>
</dtos>
```

**Step 2: Generate**

```bash
vendor/bin/dto generate
```

**Step 3: Update usage**

```php
use App\Dto\UserDto;

// Same constructor syntax works
$dto = new UserDto([
    'id' => 1,
    'name' => 'John',
]);

// But now use getters instead of public properties
$name = $dto->getName();  // Instead of $dto->name
```

### Key Differences

| Feature | spatie/data-transfer-object | php-collective/dto |
|---------|---------------------------|-------------------|
| Properties | Public | Protected + getters/setters |
| Validation | In-class | Configuration + external |
| Casters | Attribute-based | Factory methods |
| Collections | Manual array typing | Built-in collection support |

### Migrating Casters

**Before:**

```php
use Spatie\DataTransferObject\Caster;

class DateCaster implements Caster
{
    public function cast(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }
}

class EventDto extends DataTransferObject
{
    #[CastWith(DateCaster::class)]
    public DateTimeImmutable $date;
}
```

**After:**

```xml
<field name="date" type="\DateTimeImmutable"/>
```

For custom classes:

```xml
<field name="money" type="\Money\Money" factory="Money\Parser::parse"/>
```

---

## From spatie/laravel-data

### Before (spatie/laravel-data)

```php
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;

class UserData extends Data
{
    public function __construct(
        #[Required]
        public int $id,
        #[Required]
        public string $name,
        #[Email]
        public ?string $email = null,
    ) {}
}

// Usage
$data = UserData::from($request);
$data = UserData::from(['id' => 1, 'name' => 'John']);
```

### After (php-collective/dto)

```xml
<dto name="User">
    <field name="id" type="int" required="true"/>
    <field name="name" type="string" required="true"/>
    <field name="email" type="string"/>
</dto>
```

```php
// Similar static constructor
$dto = UserDto::createFromArray(['id' => 1, 'name' => 'John']);

// Or from request (you handle validation separately)
$validated = $request->validate([
    'id' => 'required|integer',
    'name' => 'required|string',
    'email' => 'nullable|email',
]);
$dto = new UserDto($validated);
```

### Key Differences

| Feature | spatie/laravel-data | php-collective/dto |
|---------|--------------------|--------------------|
| Validation | Built-in attributes | External (see Validation.md) |
| Laravel integration | Deep | Via collection factory |
| TypeScript | Built-in | Built-in |
| Lazy properties | Yes | No (use lazy loading in service layer) |
| Data pipes | Yes | No (use service layer) |

### Migrating Nested Data

**Before:**

```php
class OrderData extends Data
{
    public function __construct(
        public CustomerData $customer,
        /** @var ItemData[] */
        public array $items,
    ) {}
}
```

**After:**

```xml
<dto name="Order">
    <field name="customer" type="Customer"/>
    <field name="items" type="Item[]" collection="true" singular="item"/>
</dto>
```

### Migrating Casts

**Before:**

```php
class ProductData extends Data
{
    public function __construct(
        public string $name,
        #[WithCast(MoneyCast::class)]
        public Money $price,
    ) {}
}
```

**After:**

```xml
<dto name="Product">
    <field name="name" type="string"/>
    <field name="price" type="\Money\Money" factory="Money\Parser::parse"/>
</dto>
```

---

## From cuyz/valinor

### Before (cuyz/valinor)

```php
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $email = null,
    ) {}
}

$mapper = (new MapperBuilder())->mapper();

try {
    $user = $mapper->map(User::class, $source);
} catch (MappingError $e) {
    // Handle error
}
```

### After (php-collective/dto)

```xml
<dto name="User" immutable="true">
    <field name="id" type="int" required="true"/>
    <field name="name" type="string" required="true"/>
    <field name="email" type="string"/>
</dto>
```

```php
try {
    $dto = new UserDto($source);
} catch (InvalidArgumentException $e) {
    // Handle error
}
```

### Key Differences

| Feature | cuyz/valinor | php-collective/dto |
|---------|-------------|-------------------|
| Approach | Runtime mapping | Code generation |
| Type support | Excellent (generics, shapes) | Good (generics, union) |
| Error messages | Detailed | Basic |
| Performance | Moderate (cached) | Best (no reflection) |

---

## From Native PHP readonly Classes

### Before (PHP 8.2+)

```php
final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email = null,
    ) {}
}

$dto = new UserDto(id: 1, name: 'John');
```

### After (php-collective/dto)

```xml
<dto name="User" immutable="true">
    <field name="id" type="int" required="true"/>
    <field name="name" type="string" required="true"/>
    <field name="email" type="string"/>
</dto>
```

```php
// Array constructor (more flexible for API/form data)
$dto = new UserDto(['id' => 1, 'name' => 'John']);

// Access via getters
$name = $dto->getName();

// Immutable updates with with*() methods
$updated = $dto->withEmail('john@example.com');
```

### Benefits of Migration

- **Key format conversion**: Automatic snake_case ↔ camelCase
- **Collection support**: Built-in typed collections
- **toArray()**: Easy serialization with format options
- **touchedToArray()**: Partial updates
- **Deep cloning**: `clone()` method
- **Nested DTOs**: Automatic hydration from arrays

---

## General Migration Steps

### 1. Install php-collective/dto

```bash
composer require php-collective/dto
```

### 2. Create Configuration

Start with your most-used DTOs. Create `config/dto.xml` (or yaml/neon/php):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <!-- Define your DTOs here -->
</dtos>
```

### 3. Generate DTOs

```bash
vendor/bin/dto generate --dry-run --verbose  # Preview
vendor/bin/dto generate                       # Generate
```

### 4. Update Usages

Search and replace in your codebase:

| Old Pattern | New Pattern |
|-------------|-------------|
| `$dto->property` | `$dto->getProperty()` |
| `$dto->property = $value` | `$dto->setProperty($value)` |
| `new Dto($arg1, $arg2)` | `new Dto(['field1' => $arg1, 'field2' => $arg2])` |

### 5. Handle Validation Separately

If your old DTOs had built-in validation, extract it to a validation layer:

```php
// Before: validation in DTO
$dto = new UserDto($data);  // Validated internally

// After: explicit validation
$validated = $this->validator->validate($data, $rules);
$dto = new UserDto($validated);
```

See [Validation.md](Validation.md) for integration examples.

### 6. Test Thoroughly

- Run your existing test suite
- Add tests for new DTO behavior
- Test edge cases (null values, empty collections, etc.)

---

## Feature Comparison Quick Reference

| Feature | spatie/dto | laravel-data | valinor | php-collective/dto |
|---------|-----------|--------------|---------|-------------------|
| **Approach** | Runtime | Runtime | Runtime | Generated |
| **Immutable** | No | Optional | Yes | Optional |
| **Collections** | Manual | Built-in | Built-in | Built-in |
| **Validation** | Basic | Full | Good | Required only |
| **TypeScript** | No | Yes | No | Yes |
| **Key conversion** | No | Manual | No | Built-in |
| **IDE support** | Good | Good | Good | Excellent |
| **Static analysis** | Good | Good | Excellent | Excellent |
| **Performance** | Good | Moderate | Moderate | Best |
| **Framework** | Any | Laravel | Any | Any |
