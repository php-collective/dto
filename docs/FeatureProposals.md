# Feature Proposals & Gap Analysis

This document analyzes feature gaps compared to popular PHP DTO libraries and proposes potential enhancements.

## Comparison Libraries

- [spatie/laravel-data](https://github.com/spatie/laravel-data) - Laravel-specific, feature-rich
- [cuyz/valinor](https://github.com/CuyZ/Valinor) - Framework-agnostic, strong type support
- [symfony/serializer](https://symfony.com/doc/current/serializer.html) - Mature, extensive ecosystem

> Note: [spatie/data-transfer-object](https://github.com/spatie/data-transfer-object) is deprecated in favor of the above alternatives.

---

## Current Library Strengths

| Feature | Status | Notes |
|---------|--------|-------|
| Code generation (zero runtime reflection) | ✅ | Unique advantage |
| Perfect IDE autocomplete | ✅ | Real methods, not magic |
| Static analysis compatible | ✅ | PHPStan/Psalm friendly |
| Framework agnostic | ✅ | Works anywhere |
| Multiple config formats (XML/YAML/NEON/PHP) | ✅ | Flexible |
| TypeScript generation | ✅ | Frontend integration |
| Immutable DTOs | ✅ | `with*()` methods |
| Collection handling | ✅ | ArrayObject, custom types |
| Union types | ✅ | PHP 8.0+ |
| Enum support | ✅ | Unit & backed enums |
| Nested DTOs | ✅ | Full hierarchy support |
| Key type conversion | ✅ | camelCase/snake_case/dash-case |

---

## Feature Gap Analysis

### 1. Validation System

**Gap**: No built-in validation beyond `required` fields.

**Competitors offer**:
- **laravel-data**: Auto-inferred rules from types, validation attributes (`#[Min]`, `#[Max]`, `#[Email]`, etc.)
- **valinor**: Validation during mapping with precise error messages

**Proposal**: Attribute-based validation rules

```php
// In DTO config (PHP format)
'User' => [
    'fields' => [
        'email' => [
            'type' => 'string',
            'required' => true,
            'rules' => ['email', 'max:255'],  // NEW
        ],
        'age' => [
            'type' => 'int',
            'rules' => ['min:0', 'max:150'],  // NEW
        ],
    ],
],
```

**Generated code could include**:
```php
public function validate(): ValidationResult
{
    $errors = [];
    if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'][] = 'Invalid email format';
    }
    // ...
    return new ValidationResult($errors);
}
```

**Priority**: High
**Complexity**: Medium
**Breaking**: No (additive)

---

### 2. Property Mapping / Renaming

**Gap**: No attribute-based property mapping from different source names.

**Competitors offer**:
- **laravel-data**: `#[MapFrom('source_name')]`, `#[MapTo('output_name')]`
- **valinor**: Constructor parameter mapping

**Proposal**: Add `mapFrom` and `mapTo` field options

```php
'User' => [
    'fields' => [
        'emailAddress' => [
            'type' => 'string',
            'mapFrom' => 'email',           // Input: email -> emailAddress
            'mapTo' => 'email_address',     // Output: emailAddress -> email_address
        ],
    ],
],
```

**Priority**: Medium
**Complexity**: Low
**Breaking**: No (additive)

---

### 3. Custom Casters / Transformers

**Gap**: Limited casting - only factory methods available.

**Competitors offer**:
- **laravel-data**: Global casts, property-specific casts, bidirectional cast+transform
- **valinor**: Type converters, custom constructors

**Proposal**: Dedicated cast/transform configuration

```php
'Order' => [
    'fields' => [
        'amount' => [
            'type' => 'Money',              // Value object
            'cast' => 'MoneyCast::class',   // NEW: Input transformation
            'transform' => 'cents',         // NEW: Output transformation
        ],
        'createdAt' => [
            'type' => 'DateTimeImmutable',
            'cast' => 'datetime:Y-m-d',     // NEW: Format-aware casting
        ],
    ],
],
```

**Priority**: Medium
**Complexity**: Medium
**Breaking**: No (additive)

---

### 4. Lazy Properties / Partial Loading

**Gap**: No support for deferred loading or conditional inclusion.

**Competitors offer**:
- **laravel-data**: `Lazy::create()`, `Lazy::when()`, include/exclude control

**Proposal**: Lazy field configuration

```php
'User' => [
    'fields' => [
        'posts' => [
            'type' => 'Post[]',
            'lazy' => true,                 // NEW: Not included by default
            'lazyResolver' => 'loadPosts',  // NEW: Method to resolve
        ],
    ],
],
```

```php
// Usage
$user->toArray();                    // posts not included
$user->toArray(include: ['posts']); // posts loaded and included
```

**Priority**: Low
**Complexity**: High
**Breaking**: No (additive)

---

### 5. Computed / Virtual Properties

**Gap**: No support for derived/computed properties.

**Competitors offer**:
- **laravel-data**: Computed properties via methods

**Proposal**: Computed field support

```php
'User' => [
    'fields' => [
        'firstName' => ['type' => 'string'],
        'lastName' => ['type' => 'string'],
        'fullName' => [
            'type' => 'string',
            'computed' => true,              // NEW
            'getter' => 'getFullName',       // NEW: Custom getter method
        ],
    ],
],
```

**Generated code**:
```php
public function getFullName(): string
{
    return $this->firstName . ' ' . $this->lastName;
}
```

**Priority**: Low
**Complexity**: Medium
**Breaking**: No (additive)

---

### 6. Advanced Type Support

**Gap**: Limited compared to Valinor's type system.

**Competitors offer**:
- **valinor**: `list<string>`, `non-empty-string`, `positive-int`, `int<0, 42>`, shaped arrays

**Proposal**: Extended type hints (primarily for validation/documentation)

```php
'Product' => [
    'fields' => [
        'quantity' => [
            'type' => 'int',
            'constraints' => ['positive'],   // NEW: positive-int equivalent
        ],
        'tags' => [
            'type' => 'string[]',
            'constraints' => ['non-empty'],  // NEW: non-empty array
        ],
    ],
],
```

**Priority**: Low
**Complexity**: Medium
**Breaking**: No (additive)

---

### 7. Input Normalization Pipeline

**Gap**: No pre-processing pipeline for input data.

**Competitors offer**:
- **laravel-data**: Normalizers, pipelines for data transformation before hydration

**Proposal**: Normalizer configuration

```php
'User' => [
    'normalizers' => [                      // NEW
        'trim_strings',
        'null_empty_strings',
        'CustomNormalizer::class',
    ],
    'fields' => [
        // ...
    ],
],
```

**Priority**: Medium
**Complexity**: Medium
**Breaking**: No (additive)

---

### 8. Better Error Messages

**Gap**: Generic `InvalidArgumentException` messages.

**Competitors offer**:
- **valinor**: Precise, human-readable error messages with path information

**Proposal**: Structured error reporting

```php
try {
    $dto = UserDto::create($data);
} catch (DtoValidationException $e) {
    $e->getErrors();
    // [
    //     'email' => ['Invalid email format'],
    //     'address.zipCode' => ['Must be 5 digits'],
    // ]

    $e->getErrorTree();
    // Nested structure for complex DTOs
}
```

**Priority**: High
**Complexity**: Medium
**Breaking**: Potentially (new exception type)

---

### 9. Serialization Formats

**Gap**: Only JSON serialization built-in.

**Competitors offer**:
- **symfony/serializer**: XML, CSV, YAML serialization
- **laravel-data**: Multiple output formats

**Proposal**: Pluggable serialization

```php
$dto->toJson();
$dto->toXml();                              // NEW
$dto->toCsv();                              // NEW (for collections)
$dto->serialize('custom-format');           // NEW: Custom serializers
```

**Priority**: Low
**Complexity**: Medium
**Breaking**: No (additive)

---

### 10. Hooks / Lifecycle Events

**Gap**: No lifecycle hooks during hydration/serialization.

**Competitors offer**:
- **laravel-data**: Magic methods like `prepareForPipeline()`

**Proposal**: Hook configuration

```php
'User' => [
    'hooks' => [                            // NEW
        'beforeCreate' => 'normalizeData',
        'afterCreate' => 'logCreation',
        'beforeToArray' => 'computeValues',
    ],
    'fields' => [
        // ...
    ],
],
```

**Priority**: Low
**Complexity**: Medium
**Breaking**: No (additive)

---

## Implementation Roadmap Suggestion

### Phase 1: Quick Wins
1. Property mapping (`mapFrom`/`mapTo`)
2. Better error messages with structured exceptions

### Phase 2: Validation
3. Basic validation rules
4. Input normalizers

### Phase 3: Advanced Features
5. Custom casters/transformers
6. Computed properties
7. Advanced type constraints

### Phase 4: Extended Capabilities
8. Lazy properties
9. Additional serialization formats
10. Lifecycle hooks

---

## Decision Factors

When prioritizing features, consider:

| Factor | Weight |
|--------|--------|
| User requests / issues | High |
| Competitive parity | Medium |
| Implementation complexity | Medium |
| Breaking change risk | High |
| Maintenance burden | Medium |

---

## Non-Goals

Some features are intentionally out of scope:

1. **ORM/Database integration** - Keep library focused on data transfer
2. **Full validation framework** - Integrate with existing validators instead
3. **Request handling** - Framework-specific, use adapters
4. **Dependency injection** - Use framework DI containers

---

## References

- [spatie/laravel-data Documentation](https://spatie.be/docs/laravel-data/v4/introduction)
- [CuyZ/Valinor Documentation](https://valinor.cuyz.io/latest/)
- [Symfony Serializer](https://symfony.com/doc/current/serializer.html)
- [Deprecating spatie/data-transfer-object](https://stitcher.io/blog/deprecating-spatie-dto)
