# Schema Importer

The Importer utility helps you quickly bootstrap DTO configurations from existing JSON data or JSON Schema definitions. This is useful when integrating with external APIs or migrating from other systems.

## Quick Start

```php
use PhpCollective\Dto\Importer\Importer;

$importer = new Importer();

// From JSON data example with nested object
$json = '{
    "id": 1,
    "name": "John",
    "email": "john@example.com",
    "address": {
        "street": "123 Main St",
        "city": "New York",
        "zipCode": "10001"
    }
}';
echo $importer->import($json);
```

Output (PHP config format):
```php
<?php

use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\DtoBuilder;
use PhpCollective\Dto\Config\Field;

return DtoBuilder::create()
    ->dtos(
        Dto::create('Object')->fields(
            Field::int('id'),
            Field::string('name'),
            Field::string('email'),
            Field::dto('address', 'Address'),
        ),
        Dto::create('Address')->fields(
            Field::string('street'),
            Field::string('city'),
            Field::string('zipCode'),
        ),
    )
    ->build();
```

## Input Types

The Importer supports two input types:

### 1. JSON Data (auto-detected)

Pass an example JSON response and the importer will infer types:

```php
$json = '{
    "user": {
        "name": "John",
        "email": "john@example.com"
    },
    "items": [
        {"id": 1, "name": "Item 1"},
        {"id": 2, "name": "Item 2"}
    ]
}';

$result = $importer->import($json);
```

Features:
- Infers scalar types (string, int, float, bool)
- Detects nested objects and creates separate DTOs
- Detects arrays of objects (collections)
- Auto-detects potential associative array keys (slug, name, id, login)
- Converts field names to camelCase

### 2. JSON Schema

Pass a JSON Schema definition for more precise control:

```php
$schema = json_encode([
    'type' => 'object',
    'title' => 'User',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer'],
        'email' => ['type' => 'string'],
    ],
    'required' => ['name', 'email'],
]);

$result = $importer->import($schema);
```

Features:
- Uses `title` for DTO name
- Respects `required` array for field validation
- Handles `anyOf`/`oneOf` type unions
- Resolves `$ref` pointers to `$defs`, `definitions`, and `components/schemas`
- Resolves external file `$ref` pointers when `basePath` is provided
- Normalizes types (integer→int, boolean→bool, number→float)

### 3. OpenAPI Documents

Pass an OpenAPI 3.x specification and all schemas from `components/schemas` will be imported:

```php
$openapi = json_encode([
    'openapi' => '3.0.0',
    'info' => ['title' => 'My API', 'version' => '1.0.0'],
    'components' => [
        'schemas' => [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'profile' => ['$ref' => '#/components/schemas/Profile'],
                ],
                'required' => ['id', 'name'],
            ],
            'Profile' => [
                'type' => 'object',
                'properties' => [
                    'bio' => ['type' => 'string'],
                    'avatar' => ['type' => 'string'],
                ],
            ],
        ],
    ],
]);

$result = $importer->import($openapi);
```

Features:
- Auto-detects OpenAPI documents (requires `openapi` key and `components/schemas`)
- Parses all object schemas from `components/schemas`
- Resolves `$ref` pointers between schemas
- Handles collections with `$ref` in array items
- Skips non-object schemas (enums, primitives)

### 4. Schema Inheritance (allOf)

JSON Schema's `allOf` keyword is used to express inheritance. When a schema uses `allOf` with a `$ref`, the importer creates a DTO that extends the referenced type:

```php
$schema = json_encode([
    '$defs' => [
        'BaseEntity' => [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ],
    ],
    'type' => 'object',
    'title' => 'User',
    'allOf' => [
        ['$ref' => '#/$defs/BaseEntity'],
        [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
            'required' => ['email'],
        ],
    ],
]);

$result = $importer->import($schema);
```

Output (PHP config format):
```php
<?php

use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\DtoBuilder;
use PhpCollective\Dto\Config\Field;

return DtoBuilder::create()
    ->dtos(
        Dto::create('BaseEntity')->fields(
            Field::int('id'),
            Field::string('createdAt'),
        ),
        Dto::create('User')->extends('BaseEntity')->fields(
            Field::string('email')->required(),
            Field::string('name'),
        ),
    )
    ->build();
```

Features:
- Detects `allOf` with `$ref` as inheritance
- Creates separate DTOs for parent and child
- Child DTO only includes its own properties (not inherited ones)
- Merges properties from multiple inline schemas in `allOf`
- Merges `required` arrays from all schemas

## Output Formats

### PHP (default)

```php
$importer->import($json, ['format' => 'php']);
```

### XML

```php
$importer->import($json, ['format' => 'xml']);
```

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="https://php-collective.github.io/dto/schema">
    <dto name="User">
        <field name="name" type="string" required="true"/>
        <field name="email" type="string"/>
    </dto>
</dtos>
```

### YAML

```php
$importer->import($json, ['format' => 'yaml']);
```

```yaml
User:
  fields:
    name:
      type: string
      required: true
    email:
      type: string
```

### NEON

```php
$importer->import($json, ['format' => 'neon']);
```

## Options

| Option | Description | Default |
|--------|-------------|---------|
| `type` | Force parser type: `'Data'` or `'Schema'` | Auto-detected |
| `format` | Output format: `'php'`, `'xml'`, `'yaml'`, `'neon'` | `'php'` |
| `namespace` | Namespace prefix for generated DTOs | None |
| `basePath` | Base directory for external file `$ref` resolution | None |
| `refResolver` | Custom `$ref` resolver (implements `RefResolverInterface`) | Default file resolver |

### Namespace Example

```php
$importer->import($json, ['namespace' => 'Api/Response']);
```

Generates DTOs like `Api/Response/User`, `Api/Response/Address`, etc.

### External `$ref` Example

```php
$schema = json_encode([
    'type' => 'object',
    'properties' => [
        'user' => ['$ref' => 'schemas/user.json#/definitions/User'],
    ],
]);

$result = $importer->import($schema, ['basePath' => __DIR__ . '/openapi']);
```

## Step-by-Step Usage

For more control, you can use the two-step process:

```php
// Step 1: Parse to intermediate format
$definitions = $importer->parse($json, ['type' => 'Data']);

// Inspect or modify definitions
var_dump($definitions);
// [
//     'User' => [
//         'name' => ['type' => 'string'],
//         'age' => ['type' => 'int'],
//     ],
// ]

// Step 2: Build schema output
$schema = $importer->buildSchema($definitions, ['format' => 'xml']);
```

### Working with Arrays

```php
// Parse array directly (no JSON encoding needed)
$data = ['name' => 'John', 'age' => 30];
$definitions = $importer->parseArray($data);

// Or use the convenience method
$schema = $importer->importArray($data, ['format' => 'php']);
```

## Type Inference

### From JSON Data

| JSON Value | Inferred Type |
|------------|---------------|
| `"string"` | `string` |
| `123` | `int` |
| `12.34` | `float` |
| `true`/`false` | `bool` |
| `null` | `mixed` |
| `{...}` | Nested DTO |
| `[{...}, {...}]` | Collection (`Dto[]`) |
| `["a", "b"]` | `array` |

### From JSON Schema

| JSON Schema Type | DTO Type |
|------------------|----------|
| `string` | `string` |
| `integer` | `int` |
| `number` | `float` |
| `boolean` | `bool` |
| `array` (of objects) | Collection |
| `object` | Nested DTO |
| `["string", "null"]` | `string` (optional) |

### Format Specifiers

| JSON Schema Format | DTO Type |
|--------------------|----------|
| `date-time` | `\DateTimeInterface` |
| `date` | `\DateTimeInterface` |
| `email`, `uri`, `uuid`, etc. | `string` (unchanged) |

Note: Only `date-time` and `date` formats are mapped to class types. Other formats like `email`, `uri`, `uuid` remain as `string` since they are validation hints rather than type indicators.

### Enums with `x-enum-class`

The importer can map JSON Schema enums to PHP enums if you provide an explicit class hint via a vendor extension.

```json
{
  "type": "object",
  "properties": {
    "status": {
      "type": "string",
      "enum": ["pending", "confirmed"],
      "x-enum-class": "App\\Enum\\OrderStatus"
    }
  }
}
```

The importer will set the field type to `\App\Enum\OrderStatus` in the generated config.

## Limitations

The importer is a scaffolding tool. Generated configs typically need manual refinement:

1. **Enum detection needs a hint** - Use `x-enum-class` or update the field manually
2. **No custom types** - DateTime, custom classes need manual configuration
3. **Limited validation** - Only `required` from JSON Schema is used
4. **Type inference from data** - Single example may not represent all cases
5. **Remote `$ref` not supported** - URL references (http/https) require a custom resolver

Example:
```json
{
  "type": "object",
  "properties": {
    "status": {
      "type": "string",
      "enum": ["pending", "confirmed"]
    }
  }
}
```

The importer will generate a `string` field for `status`. You must update it to `Field::enum(...)` (or set `type` to your enum class) in the DTO config.

## Example: API Response

```php
// Fetch from API
$response = file_get_contents('https://api.example.com/users/1');

// Generate DTO config
$importer = new Importer();
$config = $importer->import($response, [
    'namespace' => 'Api/User',
    'format' => 'php',
]);

// Save to config file
file_put_contents('config/dto/api-user.php', $config);
```

Then review and refine the generated config:
- Add `required()` to mandatory fields
- Change types for enums, dates, etc.
- Add `mapFrom()`/`mapTo()` for field name mapping
- Configure `factory()`/`serialize()` for custom types
