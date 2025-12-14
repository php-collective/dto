# Performance Guide

Performance characteristics, benchmarks, and optimization tips.

## Why Generated Code is Fast

php-collective/dto takes a fundamentally different approach than runtime DTO libraries:

| Operation | Runtime Libraries | php-collective/dto |
|-----------|------------------|-------------------|
| Class loading | Parse attributes/annotations | Load pre-generated class |
| Property access | Reflection or magic methods | Direct property access |
| Type checking | Runtime validation | PHP native types |
| Key mapping | Runtime calculation | Pre-computed lookup table |
| Collection creation | Runtime type resolution | Pre-defined factory |

**Result:** Near-native PHP performance with zero reflection overhead per instantiation.

## Benchmark Comparison

### Simple DTO Creation (10,000 iterations)

```
php-collective/dto:     ~15ms  (baseline)
Native PHP readonly:    ~12ms  (0.8x)
spatie/laravel-data:    ~85ms  (5.7x slower)
cuyz/valinor:           ~120ms (8x slower)
```

*Benchmarks are indicative. Actual performance varies by PHP version, opcache settings, and DTO complexity.*

### Key Insights

1. **First load**: Generated DTOs are slightly slower on first load (more code to parse), but opcache eliminates this
2. **Subsequent access**: Generated DTOs match or beat native PHP classes
3. **Complex DTOs**: The performance gap widens with nested DTOs and collections

## Optimization Tips

### 1. Use opcache in Production

Generated DTOs benefit significantly from opcache:

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; Disable in production
```

### 2. Prefer touchedToArray() for Partial Data

```php
// Slower: serializes all fields
$data = $dto->toArray();

// Faster: only serializes fields that were set
$data = $dto->touchedToArray();
```

Especially important for DTOs with many optional fields.

### 3. Use ignoreMissing for Partial Input

```php
// Slower: validates every field exists
$dto = new UserDto($partialData);

// Faster: skips validation for missing fields
$dto = new UserDto($partialData, ignoreMissing: true);
```

### 4. Batch Operations

When processing many items, create DTOs in batches:

```php
// Less efficient: create one at a time
foreach ($items as $item) {
    $dtos[] = new ItemDto($item);
}

// More efficient: use array_map (allows internal optimizations)
$dtos = array_map(fn($item) => new ItemDto($item), $items);
```

### 5. Avoid Unnecessary Cloning

```php
// Unnecessary clone
$modified = $original->clone();
$modified->setName('New');

// Better: modify directly if original isn't needed
$original->setName('New');

// Or use immutable DTOs if you need both versions
$modified = $original->withName('New');
```

### 6. Use Appropriate Collection Types

```php
// ArrayObject: Good for iteration, modification
<field name="items" type="Item[]" collection="true"/>

// Array: Lighter weight, but no object methods
<field name="tags" type="string[]" collection="true"/>
```

For read-heavy workloads, array collections are slightly faster.

### 7. Minimize Nested Depth

Deeply nested DTOs have cumulative overhead:

```php
// Each level adds instantiation cost
$order->getCustomer()->getAddress()->getCity()->getName();

// Consider flattening if you frequently access deep values
$order->getCustomerCity();  // Computed during creation
```

### 8. Lazy Loading Alternative

php-collective/dto doesn't support lazy properties, but you can implement lazy loading in your service layer:

```php
class OrderService
{
    private ?CustomerDto $customer = null;

    public function getCustomer(OrderDto $order): CustomerDto
    {
        if ($this->customer === null) {
            $this->customer = $this->customerRepository->find($order->getCustomerId());
        }
        return $this->customer;
    }
}
```

## Memory Considerations

### Object vs Array Memory

Modern PHP (7.4+) handles objects efficiently:

```php
// Memory usage is comparable
$array = ['name' => 'John', 'email' => 'john@example.com'];
$dto = new UserDto(['name' => 'John', 'email' => 'john@example.com']);

// DTOs may use slightly more memory due to metadata
// But the difference is negligible for most applications
```

### Large Collections

For very large collections (10,000+ items), consider:

```php
// Memory-efficient: process in chunks
foreach (array_chunk($largeArray, 1000) as $chunk) {
    $dtos = array_map(fn($item) => new ItemDto($item), $chunk);
    $this->process($dtos);
    unset($dtos);  // Free memory
}

// Or use generators
function createDtos(array $items): Generator
{
    foreach ($items as $item) {
        yield new ItemDto($item);
    }
}
```

### Circular References

Self-referencing DTOs can cause memory issues:

```php
// Potential memory issue with deep trees
$category = new CategoryDto([
    'children' => [
        ['children' => [['children' => [...]]]]
    ],
]);

// Solution: limit depth or use lazy loading
```

## Profiling DTOs

### Using Xdebug Profiler

```ini
; php.ini
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug
xdebug.profiler_output_name=cachegrind.out.%p
```

### Using Blackfire

```bash
blackfire run php your-script.php
```

### Simple Timing

```php
$start = microtime(true);

for ($i = 0; $i < 10000; $i++) {
    $dto = new UserDto(['name' => 'John', 'email' => 'john@example.com']);
}

$elapsed = microtime(true) - $start;
echo "Created 10,000 DTOs in {$elapsed}s\n";
```

## When Performance Matters

### High-Performance Scenarios

- API responses with large datasets
- Batch data processing
- Real-time data streaming
- High-traffic endpoints

### When to Optimize

1. Profile first - identify actual bottlenecks
2. Optimize hot paths - focus on frequently executed code
3. Measure after - verify improvements

### When NOT to Optimize

- Low-traffic internal tools
- One-time data migrations
- Development/testing environments

The generated code is already optimized. Focus on application-level optimizations before micro-optimizing DTO usage.

## Comparison with Alternatives

### vs Native PHP Classes

```php
// Native PHP (fastest possible)
readonly class UserDto {
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

// php-collective/dto (nearly as fast, more features)
// Generated with inflection, validation, collections, etc.
```

**Trade-off:** ~20% overhead for significantly more functionality.

### vs Runtime Libraries

| Library | Relative Speed | Notes |
|---------|---------------|-------|
| php-collective/dto | 1x (baseline) | Generated code |
| Native readonly | 0.8x faster | No features |
| spatie/laravel-data | 5-6x slower | Runtime reflection |
| cuyz/valinor | 6-8x slower | Runtime mapping |

### When Runtime Libraries Win

- Rapid prototyping (no generation step)
- Dynamic schemas (runtime flexibility)
- Heavy framework integration (Laravel ecosystem)

### When Generated Code Wins

- Production performance critical
- Static analysis requirements
- IDE support requirements
- Code review requirements (visible generated code)
