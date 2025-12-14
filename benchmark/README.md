# DTO Benchmarks

Performance benchmarks for `php-collective/dto`.

## Quick Start

```bash
# Generate benchmark DTOs
bin/dto generate --config-path=benchmark/config --src-path=benchmark/src --namespace=Benchmark

# Run main benchmark suite
php benchmark/run.php

# Run with more iterations
php benchmark/run.php --iterations=100000

# Compare with external libraries
composer require --dev spatie/data-transfer-object cuyz/valinor
php benchmark/run-external.php
```

## Files

| File                | Description                                                   |
|---------------------|---------------------------------------------------------------|
| `run.php`           | Main benchmark: php-collective/dto vs Plain PHP vs Arrays     |
| `run-external.php`  | Comparison with other DTO libraries (spatie, valinor, symfony)|
| `SUMMARY.md`        | Detailed results and analysis                                 |
| `config/dto.php`    | DTO definitions for benchmarks                                |
| `src/PlainDto/`     | Plain PHP readonly DTOs for comparison                        |
| `src/Dto/`          | Generated DTOs (gitignored, regenerate as needed)             |

## What's Tested

1. **Simple DTO Creation** - Single object with 6 fields
2. **Complex Nested DTOs** - Order with User, Address, and Items
3. **Property Access** - Getter/property read performance
4. **Serialization** - toArray() and JSON encoding
5. **Template Rendering** - Simulated view layer usage
6. **Mutable vs Immutable** - Operation comparison
7. **Collections** - addItem() operations

## Code Examples

### Creation Methods Compared

**php-collective/dto** (code generation):
```php
// Generated DTO with full type safety
$user = new UserDto([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Or using static factory
$user = UserDto::createFromArray($data);
```

**Plain PHP readonly DTO** (manual):
```php
readonly class UserDto {
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

$user = new UserDto(id: 1, name: 'John Doe', email: 'john@example.com');
```

**Plain array** (no structure):
```php
$user = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
];
```

### External Libraries

**spatie/data-transfer-object**:
```php
class UserDto extends DataTransferObject {
    public int $id;
    public string $name;
    public string $email;
}

$user = new UserDto(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
```

**cuyz/valinor**:
```php
$mapper = (new MapperBuilder())->mapper();
$user = $mapper->map(UserDto::class, $data);
```

**symfony/serializer**:
```php
$serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
$user = $serializer->denormalize($data, UserDto::class);
```

### Property Access Methods

**php-collective/dto**:
```php
$name = $user->getName();           // Getter method
$name = $user->name;                // Magic __get
```

**Plain PHP DTO**:
```php
$name = $user->name;                // Direct property access
```

**Plain array**:
```php
$name = $user['name'];              // Array access
```

## Why Both Create and Read?

**Creation benchmarks** measure:
- Object instantiation overhead
- Type validation cost
- Nested DTO handling

**Read benchmarks** measure:
- Property access patterns (getter vs direct vs array)
- Runtime overhead per access
- How overhead amortizes over multiple reads

In practice, DTOs are often created once but read many times (templates, serialization). The read benchmarks show that getter overhead becomes negligible with multiple accesses.

## Results Summary

See [SUMMARY.md](SUMMARY.md) for detailed results.

**TL;DR**: `php-collective/dto` is ~12x slower than plain PHP for creation, but:
- 4-8x **faster** than runtime reflection libraries (Spatie, Valinor, Symfony)
- Property access overhead is minimal (getters nearly as fast as direct access)
- Template rendering shows only ~1.5x overhead vs plain arrays
