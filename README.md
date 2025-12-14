# PHP DTO

[![CI](https://github.com/php-collective/dto/actions/workflows/ci.yml/badge.svg)](https://github.com/php-collective/dto/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/php-collective/dto/branch/master/graph/badge.svg)](https://codecov.io/gh/php-collective/dto)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Framework-agnostic Data Transfer Object library with **code generation** for PHP.

Unlike runtime reflection libraries, this library generates optimized DTO classes at build time, giving you:
- **Zero runtime reflection overhead**
- **Perfect IDE autocomplete** with real methods
- **Excellent static analysis** support (PHPStan/Psalm work out of the box)
- **Reviewable generated code** in pull requests

See [Motivation](docs/Motivation.md) for why code generation beats runtime reflection.

## Requirements

- PHP 8.2+

## Installation

```bash
composer require php-collective/dto
```

For NEON format support:

```bash
composer require nette/neon
```

## Quick Start

### 1. Define DTOs in XML

Create a `dto.xml` configuration file:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="https://github.com/php-collective/dto">
    <dto name="Car">
        <field name="color" type="string"/>
        <field name="isNew" type="bool"/>
        <field name="distanceTravelled" type="int"/>
        <field name="value" type="float"/>
        <field name="owner" type="Owner"/>
    </dto>

    <dto name="Owner">
        <field name="name" type="string"/>
        <field name="birthYear" type="int"/>
    </dto>

    <dto name="FlyingCar" extends="Car">
        <field name="maxAltitude" type="int"/>
    </dto>
</dtos>
```

### 2. Generate DTOs

Using the CLI (recommended):

```bash
# Place dto.xml in config/ directory, then run:
vendor/bin/dto generate

# Or specify custom paths:
vendor/bin/dto generate --config-path=dto/ --src-path=app/ --namespace=MyApp

# Preview changes without writing:
vendor/bin/dto generate --dry-run --verbose
```

Or programmatically:

```php
use PhpCollective\Dto\Engine\XmlEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\Generator;
use PhpCollective\Dto\Generator\TwigRenderer;

$config = new ArrayConfig(['namespace' => 'App']);
$engine = new XmlEngine();
$builder = new Builder($engine, $config);
$renderer = new TwigRenderer(null, $config);
$io = new ConsoleIo();

$generator = new Generator($builder, $renderer, $io, $config);
$generator->generate('config/', 'src/');
```

### 3. Use Generated DTOs

```php
use App\Dto\CarDto;
use App\Dto\OwnerDto;

// Create with setters
$carDto = new CarDto();
$carDto->setColor('black');
$carDto->setIsNew(true);

// Or create from array
$carDto = CarDto::createFromArray([
    'color' => 'red',
    'isNew' => false,
    'distanceTravelled' => 50000,
]);

// Access with getters
$color = $carDto->getColor();           // string|null
$isNew = $carDto->getIsNewOrFail();     // bool (throws if not set)

// Check existence
if ($carDto->hasOwner()) {
    $owner = $carDto->getOwner();
}

// Convert back to array
$array = $carDto->toArray();
$touched = $carDto->touchedToArray();   // Only fields that were set
```

## Features

### Available Types

```xml
<field ... type="..."/>
```

**Scalar types:** `int`, `float`, `string`, `bool`, `callable`, `resource`, `iterable`, `object`, `mixed`

**Array types:** `array`, `string[]`, `int[]`, etc.

**Objects:**
- Other DTOs (without suffix): `type="Owner"`
- Value objects and classes using FQCN: `type="\DateTime"`
- Enums: `type="\App\Enum\Status"`

**Collections:**
- `collection="true"` - as `\ArrayObject` (recommended)
- `collectionType="array"` - as plain array
- `collectionType="\Custom\Collection"` - custom collection class

### Immutable DTOs

```xml
<dto name="Article" immutable="true">
    <field name="title" type="string" required="true"/>
    <field name="content" type="string"/>
</dto>
```

```php
$article = new ArticleDto(['title' => 'Hello']);
$updated = $article->withContent('World');  // Returns new instance

// Original is unchanged
echo $article->getContent();  // null
echo $updated->getContent();  // "World"
```

### Default Values

```xml
<field name="count" type="int" defaultValue="0"/>
<field name="enabled" type="bool" defaultValue="true"/>
```

### Required Fields

```xml
<field name="email" type="string" required="true"/>
```

Required fields throw an exception if not provided during construction.

### Inflection Support

DTOs work with different key formats out of the box:

```php
// From snake_case (database/forms)
$dto->fromArray($data, false, $dto::TYPE_UNDERSCORED);

// From dash-case (URL query strings)
$dto->fromArray($data, false, $dto::TYPE_DASHED);

// To snake_case
$array = $dto->toArray($dto::TYPE_UNDERSCORED);
```

### Collections

```xml
<dto name="Cars">
    <field name="cars" type="Car[]" collection="true" singular="car"/>
</dto>
```

```php
$carsDto = new CarsDto();
$carsDto->addCar($carDtoOne);
$carsDto->addCar($carDtoTwo);
echo count($carsDto->getCars());  // 2
```

### Associative Collections

```xml
<field name="items" type="Item[]" singular="item" collection="true" associative="true"/>
```

```php
$dto->addItem('key1', $itemOne);
$dto->addItem('key2', $itemTwo);
$item = $dto->getItem('key1');
```

### Deprecations

```xml
<field name="oldField" type="string" deprecated="Use newField instead"/>
```

Methods will be marked as deprecated in your IDE.

### Deep Cloning

```php
$clone = $dto->clone();  // Deep clones nested DTOs and collections
```

### Nested Reading

```php
$value = $dto->read(['nested', 'deeply', 'field']);
$valueOrDefault = $dto->read(['path', 'to', 'field'], 'default');
```

## Collection Support

By default, collections use `\ArrayObject`. You can inject a custom collection factory:

```php
use PhpCollective\Dto\Dto\Dto;

// For CakePHP
Dto::setCollectionFactory(fn($items) => new \Cake\Collection\Collection($items));

// For Laravel
Dto::setCollectionFactory(fn($items) => collect($items));
```

## Configuration Formats

The library supports multiple configuration formats:

- **XML** (default) - with XSD validation and IDE autocomplete
- **YAML** - requires PHP YAML extension (`pecl install yaml`)
- **NEON** - requires `nette/neon`
- **PHP** - native PHP arrays, best IDE support for complex configs

### YAML Example

```yaml
# config/dto.yml
Car:
    fields:
        color: string
        isNew: bool
        distanceTravelled:
            type: int
            defaultValue: 0
        owner:
            type: Owner
            required: true
        parts:
            type: Part[]
            collection: true
            singular: part

Owner:
    fields:
        name: string
        birthYear: int

ImmutableConfig:
    immutable: true
    fields:
        apiKey: string
        timeout:
            type: int
            defaultValue: 30
```

### NEON Example

```neon
# config/dto.neon
Car:
    fields:
        color: string
        isNew: bool
        distanceTravelled:
            type: int
            defaultValue: 0
        owner:
            type: Owner
            required: true

Owner:
    fields:
        name: string
        birthYear: int
```

### PHP Example

```php
<?php
// config/dto.php
return [
    'Car' => [
        'fields' => [
            'color' => 'string',
            'isNew' => 'bool',
            'owner' => [
                'type' => 'Owner',
                'required' => true,
            ],
        ],
    ],
    'Owner' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
```

### PHP Fluent Builder

For better IDE autocomplete and type safety, use the fluent builder API:

```php
<?php
// config/dto.php
use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;
use PhpCollective\Dto\Config\Schema;

return Schema::create()
    ->dto(Dto::create('Car')->fields(
        Field::string('color'),
        Field::bool('isNew'),
        Field::dto('owner', 'Owner')->required(),
    ))
    ->dto(Dto::create('Owner')->fields(
        Field::string('name'),
    ))
    ->toArray();
```

See [Configuration Builder](docs/ConfigBuilder.md) for full documentation.

## Integrations

This is the standalone core library. For framework-specific integrations:

- **CakePHP**: [dereuromark/cakephp-dto](https://github.com/dereuromark/cakephp-dto) - CLI commands, View integration, and CakePHP Collection support

## Documentation

- [Configuration Builder](docs/ConfigBuilder.md) - Fluent API for defining DTOs
- [Examples](docs/Examples.md) - Practical usage patterns and recipes
- [Motivation](docs/Motivation.md) - Why code generation beats runtime reflection

## License

MIT License. See [LICENSE](LICENSE) for details.
