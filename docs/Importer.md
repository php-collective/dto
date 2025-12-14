# Schema Importer

The Importer utility helps you quickly bootstrap DTO configurations from existing JSON data or JSON Schema definitions. This is useful when integrating with external APIs or migrating from other systems.

## Quick Start

```php
use PhpCollective\Dto\Importer\Importer;

$importer = new Importer();

// From JSON data example
$json = '{"name": "John", "age": 30, "email": "john@example.com"}';
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
            Field::string('name'),
            Field::int('age'),
            Field::string('email'),
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
- Processes nested `$ref` definitions (skipped for manual handling)
- Normalizes types (integer→int, boolean→bool, number→float)

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

### Namespace Example

```php
$importer->import($json, ['namespace' => 'Api/Response']);
```

Generates DTOs like `Api/Response/User`, `Api/Response/Address`, etc.

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

## Limitations

The importer is a scaffolding tool. Generated configs typically need manual refinement:

1. **No enum detection** - Enums are imported as `string`
2. **No custom types** - DateTime, custom classes need manual configuration
3. **Limited validation** - Only `required` from JSON Schema is used
4. **Type inference from data** - Single example may not represent all cases
5. **No `$ref` resolution** - Schema references are skipped

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
