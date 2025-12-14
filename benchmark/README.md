# DTO Benchmarks

Performance benchmarks for `php-collective/dto`.

## Quick Start

```bash
# Generate benchmark DTOs
bin/dto generate \
  --config-path=benchmark/config/ \
  --src-path=benchmark/src/Generated/ \
  --namespace=Benchmark\\Generated

# Run main benchmark suite
php benchmark/run.php

# Run with more iterations
php benchmark/run.php --iterations=100000

# Compare with external libraries
php benchmark/run-external.php
```

## Files

| File | Description |
|------|-------------|
| `run.php` | Main benchmark: php-collective/dto vs Plain PHP vs Arrays |
| `run-external.php` | Comparison with other DTO libraries (valinor, symfony, jms) |
| `SUMMARY.md` | Detailed results and analysis |
| `config/dto.php` | DTO definitions for benchmarks |
| `src/PlainDto/` | Plain PHP readonly DTOs for comparison |
| `src/Generated/` | Generated DTOs (gitignored, regenerate as needed) |

## What's Tested

1. **Simple DTO Creation** - Single object with 6 fields
2. **Complex Nested DTOs** - Order with User, Address, and Items
3. **Property Access** - Getter performance
4. **Serialization** - toArray() and JSON encoding
5. **Template Rendering** - Simulated view layer usage
6. **Mutable vs Immutable** - Operation comparison
7. **Collections** - addItem() operations

## Results Summary

See [SUMMARY.md](SUMMARY.md) for detailed results.

**TL;DR**: `php-collective/dto` is ~10x slower than plain PHP for creation, but the overhead becomes negligible in real-world scenarios like template rendering. The developer experience benefits (IDE autocomplete, type safety, generated code) often outweigh the performance cost.
