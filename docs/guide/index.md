---
title: Getting Started
description: Quick start guide for PHP DTO with code generation
---

# Getting Started

This guide will have you generating type-safe DTOs in under 5 minutes.

## What is php-collective/dto?

A **code generator** that creates Data Transfer Object classes from simple configuration files. Unlike runtime libraries that use reflection, this library generates plain PHP classes at build time—giving you:

- **Zero runtime overhead** — no reflection, no magic methods
- **Perfect IDE support** — real methods with full autocomplete
- **Reviewable code** — generated classes appear in your pull requests

## Installation

```bash
composer require php-collective/dto
```

## Quick Start

### 1. Create a Configuration File

Create `config/dto.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int" required="true"/>
        <field name="name" type="string" required="true"/>
        <field name="email" type="string"/>
    </dto>
</dtos>
```

### 2. Generate the DTO

```bash
vendor/bin/dto generate
```

This creates `src/Dto/UserDto.php` with getters, setters, and array conversion methods.

### 3. Use It

```php
use App\Dto\UserDto;

// Create from array (e.g., API response, form data)
$user = UserDto::createFromArray([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Use typed getters
echo $user->getName();        // "John Doe"
echo $user->getEmail();       // "john@example.com"

// Convert back to array
$data = $user->toArray();
```

That's it! You now have a fully typed DTO with IDE autocomplete.

---

## Why Code Generation?

| Aspect | Code Generation | Runtime Reflection |
|--------|----------------|-------------------|
| **Performance** | Native PHP speed | 5-60x slower |
| **IDE Support** | Perfect (real methods) | Limited (magic) |
| **Static Analysis** | Works out of the box | Requires plugins |
| **Code Review** | See exactly what runs | Hidden behavior |

## Configuration Formats

Choose your preferred format—all generate identical DTOs:

::: code-group

```xml [XML]
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="name" type="string" required="true"/>
        <field name="email" type="string"/>
    </dto>
</dtos>
```

```php [PHP Builder]
use PhpCollective\Dto\Config\{Dto, Field, Schema};

return Schema::create()
    ->dto(Dto::create('User')->fields(
        Field::string('name')->required(),
        Field::string('email'),
    ))
    ->toArray();
```

```php [PHP Array]
return [
    'User' => [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
            'email' => 'string',
        ],
    ],
];
```

```yaml [YAML]
User:
  fields:
    name:
      type: string
      required: true
    email: string
```

```ini [NEON]
User:
    fields:
        name:
            type: string
            required: true
        email: string
```

:::

> [!TIP]
> **XML** offers XSD validation and IDE autocomplete. **PHP Builder** provides the best type safety. Choose what fits your workflow.

## Common Patterns

### Nested DTOs

```xml
<dto name="Order">
    <field name="id" type="int" required="true"/>
    <field name="customer" type="Customer" required="true"/>
    <field name="items" type="OrderItem[]" collection="true" singular="item"/>
</dto>

<dto name="Customer">
    <field name="name" type="string"/>
    <field name="email" type="string"/>
</dto>

<dto name="OrderItem">
    <field name="product" type="string"/>
    <field name="quantity" type="int"/>
</dto>
```

```php
$order = OrderDto::createFromArray($apiResponse);

// Navigate nested objects with full type safety
echo $order->getCustomer()->getName();

// Iterate collections
foreach ($order->getItems() as $item) {
    echo $item->getProduct();
}
```

### Immutable DTOs

```xml
<dto name="Config" immutable="true">
    <field name="apiKey" type="string" required="true"/>
    <field name="timeout" type="int" defaultValue="30"/>
</dto>
```

```php
$config = ConfigDto::createFromArray(['apiKey' => 'secret']);

// Immutable: returns a new instance
$updated = $config->withTimeout(60);
```

### Enums

```xml
<dto name="Task">
    <field name="status" type="\App\Enum\TaskStatus" required="true"/>
</dto>
```

```php
enum TaskStatus: string {
    case Pending = 'pending';
    case Done = 'done';
}

$task = TaskDto::createFromArray(['status' => 'pending']);
$task->getStatus(); // TaskStatus::Pending
```

## CLI Commands

```bash
# Generate DTOs (default command)
vendor/bin/dto generate

# Preview without writing
vendor/bin/dto generate --dry-run

# Custom paths
vendor/bin/dto generate --config-path=dto/ --src-path=app/

# Generate TypeScript interfaces
vendor/bin/dto typescript --output=frontend/types/

# Generate JSON Schema
vendor/bin/dto jsonschema --output=schemas/
```

## Deployment

### Recommended: Commit Generated Code

```bash
# Install as dev dependency
composer require --dev php-collective/dto
```

1. Edit configuration
2. Run `vendor/bin/dto generate`
3. Commit generated files
4. Deploy normally

**Benefits:** No generation on production, faster deploys, code review for DTOs.

### Alternative: Generate on Deploy

```bash
composer require php-collective/dto
```

Run generation as part of your deployment script. Useful when DTO definitions are dynamic.

## Exclude from Static Analysis

Generated code should be excluded from linters:

**phpcs.xml:**
```xml
<exclude-pattern>src/Dto/*</exclude-pattern>
```

**phpstan.neon:**
```ini
parameters:
    excludePaths:
        - src/Dto/*
```

## Next Steps

- [Examples](./examples) — Real-world usage patterns
- [Config Builder](./config-builder) — Fluent PHP API reference
- [Runtime API](./runtime-api) — Core DTO methods, serialization, and global options
- [Validation](./validation) — Field constraints and rules
- [Circular Dependencies](./circular-dependencies) — How to model recursive DTO graphs safely
- [TypeScript Generation](../reference/typescript) — Frontend type sync
- [JSON Schema Generation](../reference/jsonschema) — Export JSON Schema from DTO definitions
