# PHP DTO Benchmark Summary

Comprehensive performance benchmarks comparing `php-collective/dto` against plain PHP alternatives and other DTO libraries.

## Test Environment

- **PHP Version**: 8.4.15
- **Iterations**: 50,000 per test
- **Date**: 2025-12-14

## Quick Summary

| Approach | Simple DTO | Complex Nested | Property Access | toArray() | JSON |
|----------|------------|----------------|-----------------|-----------|------|
| Plain PHP readonly | **4.3M/s** | 533K/s | **8.8M/s** | 1.3M/s | 465K/s |
| php-collective/dto | 426K/s | 70K/s | 4.5M/s | 273K/s | 187K/s |
| Plain array | 62M/s | 67M/s | 6.0M/s | 65M/s | 897K/s |

**Key Finding**: `php-collective/dto` is ~10x slower than plain PHP readonly DTOs for creation, but provides significant developer experience benefits (IDE autocomplete, type safety, generated code review).

---

## Detailed Results

### 1. Simple DTO Creation (User with 6 fields)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain nested array (baseline) | 0.02 µs | 61,806,454/s | 145x |
| Plain PHP readonly DTO | 0.20 µs | 5,025,276/s | 11.8x |
| Plain PHP DTO::fromArray() | 0.22 µs | 4,571,146/s | 10.7x |
| **php-collective/dto new()** | **2.35 µs** | **426,372/s** | **1x** |
| php-collective/dto createFromArray() | 2.44 µs | 410,117/s | 0.96x |

**Analysis**: The library has overhead from metadata processing and field validation. Plain PHP is faster but requires manual boilerplate.

---

### 2. Complex Nested DTO Creation (Order with User, Address, 3 Items)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain nested array | 0.01 µs | 67,285,333/s | 961x |
| Plain PHP nested DTOs | 1.88 µs | 532,522/s | 7.6x |
| **php-collective/dto nested** | **14.28 µs** | **70,016/s** | **1x** |

**Analysis**: Nested DTO creation shows more overhead due to recursive instantiation and collection handling. Still processes 70K complex objects per second.

---

### 3. Property Access (10 reads)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain PHP property access | 0.11 µs | 8,756,167/s | 1.9x |
| Plain array access | 0.17 µs | 6,034,069/s | 1.3x |
| **php-collective/dto getters** | **0.22 µs** | **4,506,485/s** | **1x** |

**Analysis**: Getter method calls are nearly as fast as direct property access. The small overhead is negligible in real applications.

---

### 4. Serialization - toArray()

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain array (no conversion) | 0.02 µs | 64,926,211/s | 238x |
| Plain PHP toArray() | 0.79 µs | 1,262,851/s | 4.6x |
| **php-collective/dto toArray()** | **3.66 µs** | **273,321/s** | **1x** |

**Analysis**: Serialization includes nested DTO handling and metadata processing. Consider caching for repeated serialization.

---

### 5. JSON Serialization

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain array -> JSON | 1.11 µs | 896,913/s | 4.8x |
| Plain PHP DTO -> JSON | 2.15 µs | 465,182/s | 2.5x |
| **php-collective/dto -> JSON** | **5.34 µs** | **187,207/s** | **1x** |

**Analysis**: JSON serialization combines toArray() overhead with json_encode(). Still achieves 187K JSON documents per second.

---

### 6. Template Simulation (Render Order Summary)

| Benchmark | Avg Time | Ops/sec | Relative |
|-----------|----------|---------|----------|
| Plain PHP DTO template render | 0.81 µs | 1,229,756/s | 1.5x |
| Plain array template render | 1.05 µs | 949,339/s | 1.2x |
| **php-collective/dto template render** | **1.25 µs** | **801,378/s** | **1x** |

**Analysis**: In template rendering scenarios, the difference becomes minimal. Getter overhead is amortized across multiple operations.

---

### 7. Mutable vs Immutable Operations

| Benchmark | Avg Time | Ops/sec |
|-----------|----------|---------|
| Mutable DTO: setName() | 0.05 µs | 21,065,386/s |
| Immutable DTO: withName() | 0.18 µs | 5,660,811/s |

**Analysis**: Immutable operations are ~3.7x slower due to object cloning, but still very fast at 5.6M ops/sec.

---

### 8. Collection Operations

| Benchmark | Avg Time | Ops/sec |
|-----------|----------|---------|
| Plain array append | 0.09 µs | 11,622,207/s |
| php-collective/dto addItem() | 4.95 µs | 201,824/s |

**Analysis**: Collection operations include type validation and ArrayObject management.

---

## Comparison with Other Libraries

Based on typical benchmarks (results vary by configuration):

| Library | Approach | Simple DTO Creation | Notes |
|---------|----------|---------------------|-------|
| **php-collective/dto** | Code generation | ~426K/s | Pre-generated, no runtime reflection |
| **Plain PHP 8.2+** | Manual | ~4.3M/s | No features, manual boilerplate |
| **cuyz/valinor** | Runtime mapping | ~50-100K/s | Advanced types, great errors |
| **symfony/serializer** | Runtime reflection | ~30-80K/s | Multi-format, very flexible |
| **spatie/laravel-data** | Runtime reflection | ~100-200K/s | Laravel integration, TypeScript |
| **jms/serializer** | Annotation parsing | ~10-30K/s | Legacy, feature-rich |

**Note**: External library benchmarks are estimates. Run `php benchmark/run-external.php` with libraries installed for actual numbers.

---

## When Performance Matters

### Scenarios Where php-collective/dto Excels

1. **API Response Serialization** (187K JSON/s)
   - More than sufficient for most web applications
   - Typical API handles 1K-10K requests/second

2. **Template Rendering** (800K/s)
   - Minimal overhead in view layer
   - Getter calls are negligible vs I/O

3. **Batch Processing** (70K complex DTOs/s)
   - Can process millions of records per minute
   - Consider streaming for very large datasets

### Scenarios to Consider Alternatives

1. **Ultra-high frequency trading** - Use plain arrays or structs
2. **Processing billions of records** - Consider plain PHP or FFI
3. **Memory-constrained environments** - Plain arrays use less memory

---

## Recommendations

### Use php-collective/dto When:

- ✅ Developer experience matters (IDE autocomplete, type safety)
- ✅ Code review of generated DTOs is valuable
- ✅ Processing < 100K DTOs per request
- ✅ You want both mutable and immutable options
- ✅ Static analysis (PHPStan/Psalm) is important

### Consider Plain PHP When:

- ⚡ Processing millions of simple records
- ⚡ Memory is severely constrained
- ⚡ Team is comfortable with boilerplate
- ⚡ Readonly immutable DTOs are sufficient

### Consider Other Libraries When:

- 🔄 Need TypeScript generation (spatie/laravel-data)
- 🔄 Complex type mapping with generics (cuyz/valinor)
- 🔄 Multi-format serialization (symfony/serializer)

---

## Running Benchmarks

```bash
# Full benchmark suite
php benchmark/run.php --iterations=50000

# Compare with external libraries (if installed)
php benchmark/run-external.php --iterations=10000

# JSON output for processing
php benchmark/run.php --json
```

---

## Methodology

1. **Warmup**: 10% of iterations run before measurement
2. **GC**: Garbage collection triggered between tests
3. **Timing**: High-resolution timer (hrtime)
4. **Memory**: Peak memory delta measured
5. **Iterations**: 50,000 default, configurable

All benchmarks run the same logical operation to ensure fair comparison.
