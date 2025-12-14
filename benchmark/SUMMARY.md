# PHP DTO Benchmark Summary

Comprehensive performance benchmarks comparing `php-collective/dto` against plain PHP alternatives and other DTO libraries.

## Test Environment

- **PHP Version**: 8.4.15
- **Iterations**: 10,000 per test
- **Date**: 2025-12-14

## Quick Summary

| Approach                | Simple DTO | Complex Nested | Property Access | toArray() | JSON |
|-------------------------|-----------|----------------|-----------------|-----------|------|
| php-collective/dto      | 452K/s | 72K/s | 4.3M/s | 285K/s | 182K/s |
| Plain PHP readonly DTOs | 5.5M/s | 546K/s | 8.5M/s | 1.1M/s | 469K/s |
| Plain array             | 66M/s | 66M/s | 5.9M/s | 76M/s | 910K/s |

**Key Finding**: `php-collective/dto` is ~12x slower than plain PHP readonly DTOs for creation, but provides significant developer experience benefits (IDE autocomplete, type safety, generated code review).

---

## Detailed Results

### 1. Simple DTO Creation (User with 6 fields)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain nested array (baseline) | 0.02 µs | 66,302,885/s | 147x |
| Plain PHP readonly DTO | 0.18 µs | 5,451,163/s | 12.1x |
| Plain PHP DTO::fromArray() | 0.20 µs | 5,052,718/s | 11.2x |
| **php-collective/dto new()** | **2.21 µs** | **452,391/s** | **1x** |
| php-collective/dto createFromArray() | 2.51 µs | 398,937/s | 0.88x |

**Analysis**: The library has overhead from metadata processing and field validation. Plain PHP is faster but requires manual boilerplate.

---

### 2. Complex Nested DTO Creation (Order with User, Address, 3 Items)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain nested array | 0.02 µs | 66,475,218/s | 929x |
| Plain PHP nested DTOs | 1.83 µs | 545,629/s | 7.6x |
| **php-collective/dto nested** | **13.97 µs** | **71,566/s** | **1x** |

**Analysis**: Nested DTO creation shows more overhead due to recursive instantiation and collection handling. Still processes 72K complex objects per second.

---

### 3. Property Access (10 reads)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain PHP property access | 0.12 µs | 8,505,535/s | 2.0x |
| Plain array access | 0.17 µs | 5,863,765/s | 1.4x |
| **php-collective/dto getters** | **0.23 µs** | **4,325,022/s** | **1x** |

**Analysis**: Getter method calls are nearly as fast as direct property access. The small overhead is negligible in real applications.

---

### 4. Serialization - toArray()

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain array (no conversion) | 0.01 µs | 75,575,507/s | 265x |
| Plain PHP toArray() | 0.90 µs | 1,104,989/s | 3.9x |
| **php-collective/dto toArray()** | **3.51 µs** | **284,654/s** | **1x** |

**Analysis**: Serialization includes nested DTO handling and metadata processing. Consider caching for repeated serialization.

---

### 5. JSON Serialization

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain array -> JSON | 1.10 µs | 909,717/s | 5.0x |
| Plain PHP DTO -> JSON | 2.13 µs | 469,169/s | 2.6x |
| **php-collective/dto -> JSON** | **5.50 µs** | **181,720/s** | **1x** |

**Analysis**: JSON serialization combines toArray() overhead with json_encode(). Still achieves 182K JSON documents per second.

---

### 6. Template Simulation (Render Order Summary)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain PHP DTO template render | 0.78 µs | 1,282,572/s | 1.5x |
| Plain array template render | 0.84 µs | 1,186,342/s | 1.4x |
| **php-collective/dto template render** | **1.15 µs** | **867,660/s** | **1x** |

**Analysis**: In template rendering scenarios, the difference becomes minimal. Getter overhead is amortized across multiple operations.

---

### 7. Mutable vs Immutable Operations

| Benchmark | Avg Time | Ops/sec |
|-----------|----------|---------|
| Mutable DTO: setName() | 0.04 µs | 24,117,423/s |
| Immutable DTO: withName() | 0.13 µs | 7,922,311/s |

**Analysis**: Immutable operations are ~3x slower due to object cloning, but still very fast at 7.9M ops/sec.

---

### 8. Collection Operations

| Benchmark | Avg Time | Ops/sec |
|-----------|----------|---------|
| Plain array append | 0.07 µs | 13,707,662/s |
| php-collective/dto addItem() | 3.99 µs | 250,756/s |

**Analysis**: Collection operations include type validation and ArrayObject management.

---

## Comparison with Other Libraries

Actual benchmark results (run `php benchmark/run-external.php`):

| Library | Approach | Simple DTO Creation | vs php-collective/dto |
|---------|----------|---------------------|----------------------|
| **php-collective/dto** | Code generation | 429,507/s | baseline |
| **symfony/serializer denormalize()** | Runtime reflection | 110,512/s | 3.9x slower |
| **symfony/serializer deserialize()** | Runtime reflection | 96,198/s | 4.5x slower |
| **cuyz/valinor** | Runtime mapping | 54,035/s | 7.9x slower |
| **spatie/data-transfer-object** | Runtime reflection | 52,901/s | 8.1x slower |
| **cuyz/valinor (with setup)** | Runtime mapping | 5,459/s | 78.7x slower |
| **Plain PHP 8.2+** | Manual | ~5,500,000/s | 13x faster |

**Key Insights**:
- `php-collective/dto` is **4-8x faster** than runtime reflection libraries
- Code generation eliminates runtime overhead from reflection and type parsing
- Symfony Serializer is the fastest runtime library due to caching
- Valinor's "with setup" shows the cost of creating mappers on each request

---

## When Performance Matters

### Scenarios Where php-collective/dto Excels

1. **API Response Serialization** (182K JSON/s)
   - More than sufficient for most web applications
   - Typical API handles 1K-10K requests/second

2. **Template Rendering** (868K/s)
   - Minimal overhead in view layer
   - Getter calls are negligible vs I/O

3. **Batch Processing** (72K complex DTOs/s)
   - Can process millions of records per minute
   - Consider streaming for very large datasets

### Scenarios to Consider Alternatives

1. **Ultra-high frequency trading** - Use plain arrays or structs
2. **Processing billions of records** - Consider plain PHP or FFI
3. **Memory-constrained environments** - Plain arrays use less memory

---

## Recommendations

### Use php-collective/dto When:

- Developer experience matters (IDE autocomplete, type safety)
- Code review of generated DTOs is valuable
- Processing < 100K DTOs per request
- You want both mutable and immutable options
- Static analysis (PHPStan/Psalm) is important

### Consider Plain PHP When:

- Processing millions of simple records
- Memory is severely constrained
- Team is comfortable with boilerplate
- Readonly immutable DTOs are sufficient

### Consider Other Libraries When:

- Need TypeScript generation (spatie/laravel-data)
- Complex type mapping with generics (cuyz/valinor)
- Multi-format serialization (symfony/serializer)

---

## Running Benchmarks

```bash
# Generate benchmark DTOs first
bin/dto generate --config-path=benchmark/config --src-path=benchmark/src --namespace=Benchmark

# Full benchmark suite
php benchmark/run.php

# Compare with external libraries
composer require --dev cuyz/valinor
php benchmark/run-external.php

# With custom iterations
php benchmark/run.php --iterations=50000
php benchmark/run-external.php --iterations=10000
```

---

## Methodology

1. **Warmup**: 10% of iterations run before measurement
2. **GC**: Garbage collection triggered between tests
3. **Timing**: High-resolution timer (hrtime)
4. **Memory**: Peak memory delta measured
5. **Iterations**: 10,000 default, configurable

All benchmarks run the same logical operation to ensure fair comparison.
