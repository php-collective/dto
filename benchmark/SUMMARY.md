# PHP DTO Benchmark Summary

Comprehensive performance benchmarks comparing `php-collective/dto` against plain PHP alternatives and other DTO libraries.

## Test Environment

- **PHP Version**: 8.4.15
- **Iterations**: 10,000 per test
- **Date**: 2025-12-14

## Quick Summary

| Approach                | Simple DTO | Complex Nested | Property Access | toArray() | JSON |
|-------------------------|------------|----------------|-----------------|-----------|------|
| php-collective/dto      | 3.08M/s    | 500K/s         | 4.9M/s          | 288K/s    | 205K/s |
| Plain PHP readonly DTOs | 4.96M/s    | 492K/s         | 7.5M/s          | 1.3M/s    | 476K/s |
| Plain array             | 62M/s      | 73M/s          | 6.1M/s          | 66M/s     | 945K/s |

**Key Finding**: After optimizations, `php-collective/dto` is only ~1.6x slower than plain PHP readonly DTOs for creation, while providing significant developer experience benefits (IDE autocomplete, type safety, generated code review).

---

## Detailed Results

### 1. Simple DTO Creation (User with 6 fields)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain nested array (baseline) | 0.02 µs | 61,979,757/s | 20x |
| Plain PHP readonly DTO | 0.20 µs | 4,959,353/s | 1.6x |
| Plain PHP DTO::fromArray() | 0.22 µs | 4,560,165/s | 1.5x |
| **php-collective/dto new()** | **0.32 µs** | **3,077,193/s** | **1x** |
| php-collective/dto createFromArray() | 0.35 µs | 2,884,460/s | 0.94x |

**Analysis**: With optimizations, the gap with plain PHP is minimal. Direct property assignment in `setFromArrayFast()` eliminates dynamic method call overhead.

---

### 2. Complex Nested DTO Creation (Order with User, Address, 3 Items)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain nested array | 0.01 µs | 73,219,306/s | 146x |
| Plain PHP nested DTOs | 2.03 µs | 491,757/s | 0.98x |
| **php-collective/dto nested** | **2.00 µs** | **501,211/s** | **1x** |

**Analysis**: For nested DTOs, php-collective/dto is now **equal to or faster than** plain PHP DTOs! The optimized code generation produces highly efficient nested instantiation.

---

### 3. Property Access (10 reads)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain PHP property access | 0.13 µs | 7,456,921/s | 1.5x |
| Plain array access | 0.15 µs | 6,510,497/s | 1.3x |
| **php-collective/dto getters** | **0.20 µs** | **4,949,099/s** | **1x** |

**Analysis**: Getter method calls are nearly as fast as direct property access. The small overhead is negligible in real applications.

---

### 4. Serialization - toArray()

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain array (no conversion) | 0.02 µs | 65,627,133/s | 229x |
| Plain PHP toArray() | 0.78 µs | 1,280,233/s | 4.5x |
| **php-collective/dto toArray()** | **3.47 µs** | **288,312/s** | **1x** |

**Analysis**: Serialization includes nested DTO handling and metadata processing.

---

### 5. JSON Serialization

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain array -> JSON | 1.06 µs | 944,738/s | 4.6x |
| Plain PHP DTO -> JSON | 2.10 µs | 476,015/s | 2.3x |
| **php-collective/dto -> JSON** | **4.87 µs** | **205,162/s** | **1x** |

**Analysis**: JSON serialization combines toArray() overhead with json_encode(). Still achieves 205K JSON documents per second.

---

### 6. Template Simulation (Render Order Summary)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain PHP DTO template render | 0.70 µs | 1,432,142/s | 1.7x |
| Plain array template render | 0.83 µs | 1,201,853/s | 1.4x |
| **php-collective/dto template render** | **1.17 µs** | **853,060/s** | **1x** |

**Analysis**: In template rendering scenarios, the difference is minimal. Getter overhead is amortized across multiple operations.

---

### 7. Mutable vs Immutable Operations

| Benchmark | Avg Time | Ops/sec |
|-----------|----------|---------|
| Mutable DTO: setName() | 0.05 µs | 20,671,065/s |
| Immutable DTO: withName() | 0.14 µs | 7,203,345/s |

**Analysis**: Immutable operations are ~3x slower due to object cloning, but still very fast at 7.2M ops/sec.

---

### 8. Collection Operations

| Benchmark | Avg Time | Ops/sec |
|-----------|----------|---------|
| Plain array append | 0.07 µs | 13,490,343/s |
| php-collective/dto addItem() | 0.94 µs | 1,065,594/s |

**Analysis**: Collection operations include type validation and ArrayObject management.

---

## Comparison with Other Libraries

Actual benchmark results (run `php benchmark/run-external.php`):

### Simple DTO Creation

| Library                              | Ops/sec       | vs php-collective/dto |
|--------------------------------------|---------------|----------------------|
| **php-collective/dto**               | 2,784,222/s   | baseline             |
| **symfony/serializer denormalize()** | 97,534/s      | 28x slower           |
| **symfony/serializer deserialize()** | 80,641/s      | 35x slower           |
| **cuyz/valinor**                     | 51,255/s      | 54x slower           |
| **spatie/data-transfer-object**      | 50,215/s      | 55x slower           |
| **cuyz/valinor (with setup)**        | 4,254/s       | 654x slower          |
| **spatie/laravel-data**              | N/A           | Requires Laravel     |
| **Plain PHP 8.2+**                   | ~4,960,000/s  | 1.8x faster          |

### Complex Nested DTO Creation

| Library                  | Ops/sec   | Notes                                      |
|--------------------------|-----------|-------------------------------------------|
| **php-collective/dto**   | 392,763/s | Full nested Order with User, Address, Items |

### Read Operations (10 property reads)

| Library                        | Ops/sec      | Notes                    |
|--------------------------------|--------------|--------------------------|
| **spatie/data-transfer-object**| 17,971,906/s | Direct property access   |
| **php-collective/dto**         | 5,397,513/s  | Getter methods           |

### Realistic Scenario (1 Create + 10 Reads)

| Library                        | Ops/sec     | vs php-collective/dto |
|--------------------------------|-------------|----------------------|
| **php-collective/dto**         | 1,483,882/s | baseline             |
| **symfony/serializer**         | 80,966/s    | 18x slower           |
| **spatie/data-transfer-object**| 40,141/s    | 37x slower           |

**Key Insights**:
- `php-collective/dto` is **28-55x faster** than runtime reflection libraries for creation
- Read performance: Spatie's direct property access is faster, but getter overhead is minimal
- **Realistic scenario**: php-collective/dto is 18-37x faster than alternatives when combining create + read
- Code generation eliminates runtime overhead from reflection and type parsing
- `spatie/laravel-data` requires Laravel framework and cannot run standalone

---

## When Performance Matters

### Scenarios Where php-collective/dto Excels

1. **API Response Serialization** (205K JSON/s)
   - More than sufficient for most web applications
   - Typical API handles 1K-10K requests/second

2. **Template Rendering** (853K/s)
   - Minimal overhead in view layer
   - Getter calls are negligible vs I/O

3. **Batch Processing** (500K complex DTOs/s)
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

# Compare with external libraries (install dependencies first)
cd benchmark && composer install && cd ..
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
