---
title: Playground
description: Convert JSON to DTO configuration interactively
---

# DTO Playground

Paste JSON data and instantly see the generated DTO configuration. This helps you quickly bootstrap DTOs from API responses or existing data structures.

<DtoPlayground />

## How It Works

1. **Paste JSON** — Any valid JSON object
2. **Choose format** — PHP Builder, XML, or YAML
3. **Copy output** — Use in your project

## Tips

- **Nested objects** become separate DTOs
- **Arrays of objects** become collections
- **Field names** are preserved as-is (the actual generator converts to camelCase)

## For Production Use

This browser-based tool provides a quick preview. For full functionality including:

- JSON Schema parsing with `$ref` resolution
- OpenAPI document support
- External file references
- Type inference from multiple examples

Use the CLI importer:

```bash
# From JSON data
php -r "echo (new PhpCollective\Dto\Importer\Importer())->import(file_get_contents('data.json'));"

# Or save to file
vendor/bin/dto import data.json --output=config/dto.php
```

See [Schema Importer](/reference/importer) for complete documentation.
