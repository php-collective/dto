---
title: JSON Schema Generation
---

# JSON Schema Generation

Generate JSON Schema directly from your DTO configuration files.

This exporter is useful for API contracts, consumer documentation, and validation layers that need a schema artifact without reflecting over runtime DTO classes.

## Quick Start

```bash
# Single file output with $defs
vendor/bin/dto jsonschema --output=schemas/

# Multi-file output with external $ref values
vendor/bin/dto jsonschema --multi-file --output=schemas/
```

## CLI Options

```
JSON Schema Options:
  --output=PATH        Path for JSON Schema output (default: schemas/)
  --single-file        Generate all schemas in one file with $defs (default)
  --multi-file         Generate each schema in separate file
  --no-refs            Inline nested DTOs instead of using $ref
  --date-format=FMT    Date format: date-time, date, string (default: date-time)
```

See [CLI Reference](/reference/cli) for the full command overview and shared options.

## Output Modes

### Single File Output

```bash
vendor/bin/dto jsonschema --output=schemas/
```

Creates `schemas/dto-schemas.json` with a shared `$defs` section:

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "dto-schemas.json",
  "title": "DTO Schemas",
  "$defs": {
    "UserDto": {
      "type": "object",
      "properties": {
        "id": { "type": "integer" },
        "email": { "type": "string" }
      },
      "required": ["id"],
      "additionalProperties": false
    }
  }
}
```

Nested DTOs use in-document references such as `#/$defs/UserDto`.

### Multi-File Output

```bash
vendor/bin/dto jsonschema --multi-file --output=schemas/
```

Creates one file per DTO:

- `schemas/UserDto.json`
- `schemas/OrderDto.json`

Nested DTOs use external `$ref` values such as `UserDto.json`.

### Inline Nested DTOs

```bash
vendor/bin/dto jsonschema --no-refs --output=schemas/
```

When `--no-refs` is used, nested DTOs are expanded inline instead of emitted as `$ref`.

## Schema Characteristics

The generated schemas currently have these traits:

- Draft 2020-12 schema format by default
- `additionalProperties: false` on generated object schemas
- `required` only for fields marked as required in the DTO definition
- Nullable fields represented via `oneOf` with `{"type":"null"}`
- Union types represented via `oneOf`
- `mixed` represented as an empty schema (`{}`)

## Type Mapping

| DTO Type | JSON Schema |
|---------|-------------|
| `int`, `integer` | `{"type":"integer"}` |
| `float`, `double` | `{"type":"number"}` |
| `string` | `{"type":"string"}` |
| `bool`, `boolean` | `{"type":"boolean"}` |
| `array` | `{"type":"array"}` |
| `mixed` | `{}` |
| `object` | `{"type":"object"}` |
| `string[]` | `{"type":"array","items":{"type":"string"}}` |
| `int[]` | `{"type":"array","items":{"type":"integer"}}` |
| Other DTO | `$ref` or inline object schema |
| `DateTime*` | `{"type":"string","format":"date-time"}` by default |

Unknown non-DTO class types currently fall back to a generic object schema.

## Date Formats

Date-like PHP types (`DateTime`, `DateTimeImmutable`, `DateTimeInterface`) are emitted as JSON strings with a configurable format:

```bash
vendor/bin/dto jsonschema --date-format=date
vendor/bin/dto jsonschema --date-format=string
```

- `date-time`: RFC 3339 style timestamp string
- `date`: calendar date string
- `string`: plain string without a format hint

## Unions and Nullability

### Union Types

```php
Field::union('value', 'int', 'string')->required()
```

Becomes:

```json
{
  "oneOf": [
    { "type": "integer" },
    { "type": "string" }
  ]
}
```

### Nullable Fields

Optional nullable fields are wrapped in `oneOf` with `null`:

```json
{
  "oneOf": [
    { "type": "string" },
    { "type": "null" }
  ]
}
```

## Programmatic Usage

```php
use PhpCollective\Dto\Engine\XmlEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\JsonSchemaGenerator;

$config = new ArrayConfig(['namespace' => 'App']);
$engine = new XmlEngine();
$builder = new Builder($engine, $config);
$io = new ConsoleIo();

$definitions = $builder->build('config/', []);

$generator = new JsonSchemaGenerator($io, [
    'singleFile' => false,
    'schemaVersion' => 'https://json-schema.org/draft/2020-12/schema',
    'suffix' => 'Dto',
    'dateFormat' => 'date-time',
    'useRefs' => true,
]);

$generator->generate($definitions, 'schemas/');
```

### Programmatic Options

| Option | Default | Description |
|--------|---------|-------------|
| `singleFile` | `true` | Generate one `dto-schemas.json` file with `$defs` |
| `schemaVersion` | Draft 2020-12 URL | Value for the top-level `$schema` keyword |
| `suffix` | `'Dto'` | Custom suffix for generated schema names |
| `dateFormat` | `'date-time'` | Format for date-like PHP types: `date-time`, `date`, `string` |
| `useRefs` | `true` | Emit `$ref` for nested DTOs instead of inlining |

The CLI currently exposes `singleFile`, `useRefs` via `--no-refs`, and `dateFormat`.

## CI/CD Integration

```yaml
- name: Generate JSON Schema
  run: vendor/bin/dto jsonschema --output=schemas/

- name: Validate generated artifacts
  run: test -f schemas/dto-schemas.json
```

The generator fails with a descriptive error if it cannot create the output directory or write a schema file.
