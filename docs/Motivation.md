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

The PHP DTO ecosystem has evolved significantly with PHP 8.x features. Here's the current landscape (2024/2025):

**Active Libraries:**
- [spatie/laravel-data](https://github.com/spatie/laravel-data): Laravel-specific, runtime reflection with PHP 8 attributes. Features validation, TypeScript generation, and API resource integration.
- [cuyz/valinor](https://github.com/CuyZ/Valinor): Framework-agnostic runtime mapper with PHPStan/Psalm type support (generics, shaped arrays). Excellent error messages.
- [symfony/serializer](https://symfony.com/doc/current/serializer.html): Component-based serialization for Symfony. Supports JSON, XML, YAML, CSV.
- [jms/serializer](https://github.com/schmittjoh/serializer): Mature annotation-driven serializer with versioning and Doctrine integration.

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

| Aspect                  |  php-collective/dto  |    laravel-data    |     valinor     | symfony  | native PHP |
|-------------------------|:--------------------:|:------------------:|:---------------:|:--------:|:----------:|
| **Approach**            |   Code generation    | Runtime reflection | Runtime mapping | Runtime  |   Manual   |
| **IDE Autocomplete**    |      Excellent       |        Good        |      Good       |   Good   | Excellent  |
| **Static Analysis**     |      Excellent       |        Good        |    Excellent    |   Good   | Excellent  |
| **Runtime Performance** |         Best         |      Moderate      |    Moderate     | Moderate |    Best    |
| **Validation**          |        Basic         |        Full        |      Good       | Partial  |    None    |
| **TypeScript Gen**      |          No          |        Yes         |       No        |    No    |     No     |
| **Collections**         |       Built-in       |      Built-in      |    Built-in     |  Manual  |   Manual   |
| **Inflection**          |       Built-in       |       Manual       |     Manual      |  Manual  |   Manual   |
| **Framework**           |         Any          |      Laravel       |       Any       | Symfony  |    Any     |

**When to choose php-collective/dto:**
- Performance is important (API responses, batch processing)
- You want the best possible IDE and static analysis support
- You prefer configuration files over code attributes
- You need either mutable or immutable DTOs
- You work with different key formats (camelCase, snake_case, dashed)

## Summary

**Strengths vs competition:**

| Aspect              | php-collective/dto         | Others            |
|---------------------|----------------------------|-------------------|
| IDE/Static Analysis | Excellent (generated code) | Good (reflection) |
| Runtime Performance | Best (no reflection)       | Moderate          |
| Code Review         | Generated code visible     | Magic/runtime     |
| Inflection Support  | Built-in                   | Usually manual    |

**Gaps to address:**

| Feature        | php-collective/dto | laravel-data | valinor |
|----------------|:------------------:|:------------:|:-------:|
| TypeScript Gen |         No         |     Yes      |   No    |
| Validation     |       Basic        |     Full     |  Good   |
| Generics       |         No         |   Partial    |   Yes   |
| Union Types    |      Limited       |     Yes      |   Yes   |

**Verdict:** php-collective/dto is the **only code-generation approach** in the PHP DTO ecosystem, giving it unique advantages for performance and IDE support.

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

[Value objects](https://codete.com/blog/value-objects/) should work nicely with DTOs. Value objects like `DateTime`, `Money`, or custom ones are usually immutable by design.

The key difference: value objects can contain logic and "operations" between each other (`$moneyOne->subtract($moneyTwo)`), whereas a DTO must not contain anything beyond holding pure data and setting/getting.

## Performance Benchmark

Depending on the use case, arrays can be twice as fast, but memory usually remains the same or even decreases with objects.

If you're not doing millions of DTO operations per request, the benefits clearly outweigh any speed disadvantage. The code behaves more correctly and can be tested and verified more easily.

**Developer speed, code readability and code reliability strongly increase with only a bit of speed decrease** that usually doesn't matter for a normal web request.
