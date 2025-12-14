# PHP DTO

[![CI](https://github.com/php-collective/dto/actions/workflows/ci.yml/badge.svg)](https://github.com/php-collective/dto/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/php-collective/dto/branch/master/graph/badge.svg)](https://codecov.io/gh/php-collective/dto)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Framework-agnostic Data Transfer Object library with **code generation** for PHP.

Unlike runtime reflection libraries, this library generates optimized DTO classes at build time, giving you:
- **Zero runtime reflection overhead**
- **Perfect IDE autocomplete** with real methods
- **Excellent static analysis** support (PHPStan/Psalm work out of the box)
- **Reviewable generated code** in pull requests

See [Motivation](docs/Motivation.md) for why code generation beats runtime reflection.

## Installation

```bash
composer require php-collective/dto
```

## Quick Start

Define DTOs in XML (or YAML, NEON, PHP):

```xml
<dtos xmlns="https://github.com/php-collective/dto">
    <dto name="Car">
        <field name="color" type="string"/>
        <field name="owner" type="Owner"/>
    </dto>
    <dto name="Owner">
        <field name="name" type="string"/>
    </dto>
</dtos>
```

Generate and use:

```bash
vendor/bin/dto generate
```

```php
$car = CarDto::createFromArray(['color' => 'red']);
$car->setOwner(new OwnerDto(['name' => 'John']));
$array = $car->toArray();
```

See [Quick Start Guide](docs/QuickStart.md) for detailed examples.

## Features

- **Types**: `int`, `float`, `string`, `bool`, `array`, `mixed`, DTOs, classes, enums
- **Union types**: `int|string`, `int|float|string` (PHP 8.0+)
- **Collections**: `type="Item[]" collection="true"` with add/get/has methods
- **Associative collections**: keyed access with `associative="true"`
- **Immutable DTOs**: `immutable="true"` with `with*()` methods
- **Default values**: `defaultValue="0"`
- **Required fields**: `required="true"`
- **Deprecations**: `deprecated="Use newField instead"`
- **Inflection**: automatic snake_case/camelCase/dash-case conversion
- **Deep cloning**: `$dto->clone()`
- **Nested reading**: `$dto->read(['path', 'to', 'field'])`
- **PHPDoc generics**: `@var \ArrayObject<int, ItemDto>` for static analysis

## Configuration Formats

- **XML** (default) - XSD validation, IDE autocomplete
- **YAML** - requires `pecl install yaml`
- **NEON** - requires `nette/neon`
- **PHP** - native arrays or [fluent builder](docs/ConfigBuilder.md)

## Documentation

- [Quick Start Guide](docs/QuickStart.md) - Getting started with examples
- [Configuration Builder](docs/ConfigBuilder.md) - Fluent API for defining DTOs
- [Examples](docs/Examples.md) - Practical usage patterns
- [Motivation](docs/Motivation.md) - Why code generation beats runtime reflection

## Integrations

This is the standalone core library. For framework-specific integrations:

- **CakePHP**: [dereuromark/cakephp-dto](https://github.com/dereuromark/cakephp-dto)
