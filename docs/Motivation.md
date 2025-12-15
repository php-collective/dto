# Motivation and Background

## The Problem with Arrays

Working with complex and nested arrays quickly becomes problematic:
- In templates, you don't know exactly what keys are available or what the nesting structure looks like
- No IDE autocomplete or typehinting unless you're inside the same code that created the array
- No verification of fields and their types with simple associative arrays
- PHPStan and other introspection tools can't work well with untyped arrays

## The Solution: DTOs

[Data Transfer Objects (DTOs)](https://dzone.com/articles/practical-php/practical-php-patterns-data) are the best approach here, but creating them manually can be tedious.

Some people argue that arrays are faster and use less memory than objects. This might have been true in PHP 5.2, but modern PHP handles objects efficiently.

## Other Existing Solutions

The PHP DTO ecosystem has evolved significantly with PHP 8.x features. Here's the current landscape (2025):

**Active Libraries:**
- [spatie/laravel-data](https://github.com/spatie/laravel-data) (v4.18+): Laravel-specific, runtime reflection with PHP 8 attributes. Features validation, TypeScript generation, lazy properties, and Eloquent integration. Requires PHP 8.1+.
- [cuyz/valinor](https://github.com/CuyZ/Valinor) (v2.3+): Framework-agnostic runtime mapper with PHPStan/Psalm type support (generics, shaped arrays, integer ranges). Excellent error messages and normalization support. Requires PHP 8.1+.
- [symfony/serializer](https://symfony.com/doc/current/serializer.html) (v7/8): Component-based serialization with new JsonStreamer for streaming large datasets. Supports JSON, XML, YAML, CSV.
- [symfony/object-mapper](https://symfony.com/doc/current/serializer.html) (Symfony 7.3+): New lightweight ObjectMapper component for simple DTO hydration without full Serializer overhead.
- [jms/serializer](https://github.com/schmittjoh/serializer) (v3.32+): Mature annotation/attribute-driven serializer with versioning, Doctrine integration, and circular reference handling. Requires PHP 7.4+.

**Deprecated:**
- [spatie/data-transfer-object](https://github.com/spatie/data-transfer-object): **Deprecated as of 2023**. Maintainers recommend `spatie/laravel-data` or `cuyz/valinor`.

**Native PHP 8.2+ (No Library):**
```php
final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $email,
    ) {}
}
```
Sufficient for simple cases, but lacks collections, validation, and inflection support.

**Common issues with runtime libraries:**
- Runtime reflection overhead on every instantiation
- IDE support limited by "magic" - autocomplete depends on plugin quality
- Static analysis requires additional annotations or plugins

## Why Generated Code?

This library takes a fundamentally different approach: **code generation instead of runtime reflection**.

Other libraries leverage declared properties and reflection/introspection at runtime to finalize the DTO. What if we let a generator do that for us? Taking the maximum performance benefit from creating a customized object, while having all the addons we want on top for free?

We generate optimized DTOs where all inflection, reflection, validation and asserting is done at generation time. Using them is just as simple as with basic arrays, only with tons of benefits on top.

**Key advantages of code generation:**
- **Zero runtime reflection** - no performance overhead per instantiation
- **Excellent IDE support** - real methods mean perfect autocomplete and "Find Usages"
- **Perfect static analysis** - PHPStan/Psalm work without plugins or annotations
- **Reviewable code** - generated classes can be inspected in pull requests
- **No magic** - what you see is exactly what runs

## Comparison with Alternatives

| Aspect                  | php-collective/dto | laravel-data | valinor  | symfony/serializer | jms/serializer | native PHP |
|-------------------------|:------------------:|:------------:|:--------:|:------------------:|:--------------:|:----------:|
| **Approach**            |  Code generation   |  Reflection  | Mapping  |     Reflection     |   Reflection   |   Manual   |
| **IDE Autocomplete**    |     Excellent      |     Good     |   Good   |        Good        |      Good      | Excellent  |
| **Static Analysis**     |     Excellent      |     Good     | Excellent |        Good        |      Good      | Excellent  |
| **Runtime Performance** |        Best        |   Moderate   | Moderate |      Moderate      |    Moderate    |    Best    |
| **Validation**          |   Required only    |     Full     |   Good   |      Partial       |    Partial     |    None    |
| **TypeScript Gen**      |        Yes         |     Yes      |    No    |         No         |       No       |     No     |
| **Collections**         |      Built-in      |   Built-in   | Built-in |       Manual       |    Built-in    |   Manual   |
| **Inflection**          |      Built-in      |    Manual    |  Manual  |       Manual       |     Manual     |   Manual   |
| **Immutable DTOs**      |      Built-in      |   Built-in   | Built-in |       Manual       |     Manual     |   Manual   |
| **Lazy Properties**     |         No         |     Yes      |    No    |         No         |       No       |     No     |
| **Generics Support**    |    PHPDoc only     |   Partial    | Excellent|      Partial       |    Partial     |     No     |
| **Error Messages**      |       Good         |     Good     | Excellent|        Good        |      Good      |    N/A     |
| **Framework**           |        Any         |   Laravel    |   Any    |      Symfony       |      Any       |    Any     |
| **PHP Requirement**     |       8.2+         |    8.1+      |   8.1+   |        8.4+        |      7.4+      |    8.2+    |

**When to choose php-collective/dto:**
- Performance is critical (25-60x faster than runtime libraries)
- You want the best possible IDE and static analysis support
- You prefer configuration files over code attributes
- You need either mutable or immutable DTOs with explicit choice
- You work with different key formats (camelCase, snake_case, dashed)
- Code review of generated DTOs is valuable to your team

## Summary

**Strengths vs competition:**

| Aspect              | php-collective/dto           | Runtime Libraries          |
|---------------------|------------------------------|----------------------------|
| IDE/Static Analysis | Excellent (real methods)     | Good (reflection/magic)    |
| Runtime Performance | Best (25-60x faster)         | Moderate                   |
| Code Review         | Generated code visible in PR | Magic/runtime behavior     |
| Inflection Support  | Built-in (snake/camel/dash)  | Usually manual             |
| Build-time Errors   | Catch issues at generation   | Discover at runtime        |

**Gaps compared to runtime libraries:**

| Feature              | php-collective/dto | laravel-data | valinor  | jms/serializer |
|----------------------|:------------------:|:------------:|:--------:|:--------------:|
| Validation Rules     |   Required only    |     Full     |   Good   |    Partial     |
| PHPDoc Generics      |        Yes         |   Partial    | Excellent |    Partial     |
| Lazy Properties      |         No         |     Yes      |    No    |       No       |
| Shaped Arrays        |         No         |      No      |   Yes    |       No       |
| Integer Ranges       |         No         |      No      |   Yes    |       No       |
| API Versioning       |         No         |      No      |    No    |      Yes       |
| Eloquent Integration |         No         |     Yes      |    No    |       No       |
| Streaming/Large Data |         No         |      No      |    No    |       No       |

**Verdict:** php-collective/dto is the **only code-generation approach** in the PHP DTO ecosystem, giving it unique advantages for performance (25-60x faster) and IDE support. Choose runtime libraries if you need advanced validation, lazy loading, or framework-specific integration.

## Why Not Immutable by Default?

Arrays are somewhat immutable, so this is a fair point. The goal was to first make it work for easy use cases and simple usage. For most use cases, mutable objects are a good compromise - allowing easy modifications where needed.

Immutable means we either have to insert all data into the constructor or provide `with...()` methods. This should be a deliberate choice.

## Why the Dto Suffix?

A `Post` or `Article` object will likely clash with existing entities or similar classes. Having to alias in all files is not ideal. Also consider `Date` and other reserved words.

So `PostDto` etc. is easy enough to avoid issues while not being much longer. Inside code it can also be helpful to keep prefixes in variables:

```php
$postArray = [
    'title' => 'My cool Post',
];
$postDto = new PostDto($postArray);
```

This makes the code more readable in pull requests or when not directly inside the IDE.

## Why No Interfaces?

Contracting with interfaces is important when building SOLID code. For generated classes it seems like overhead. From a stability perspective, manually modified code shouldn't extend/implement fluently changing generated code. The generated classes always have to be evaluated as a whole.

## Value Objects

[Value objects](https://martinfowler.com/bliki/ValueObject.html) should work nicely with DTOs. Value objects like `DateTime`, `Money`, or custom ones are usually immutable by design.

The key difference: value objects can contain logic and "operations" between each other (`$moneyOne->subtract($moneyTwo)`), whereas a DTO must not contain anything beyond holding pure data and setting/getting.

## Performance Benchmark

Depending on the use case, arrays can be twice as fast, but memory usually remains the same or even decreases with objects.

If you're not doing millions of DTO operations per request, the benefits clearly outweigh any speed disadvantage. The code behaves more correctly and can be tested and verified more easily.

**Developer speed, code readability and code reliability strongly increase with only a bit of speed decrease** that usually doesn't matter for a normal web request.
