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

```php
use PhpCollective\Dto\Generator\Generator;
use PhpCollective\Dto\Generator\TwigRenderer;
use PhpCollective\Dto\Generator\Finder;
use PhpCollective\Dto\Generator\ArrayConfig;

$config = new ArrayConfig([
    'paths' => ['/path/to/dto/configs'],
    'namespace' => 'App\\Dto',
    'targetDir' => '/path/to/generated',
]);

$renderer = new TwigRenderer();
$finder = new Finder($config);
$generator = new Generator($config, $renderer, $finder);
$generator->generate();
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
- **YAML** - requires `symfony/yaml` (included via Twig)
- **NEON** - requires `nette/neon`

### YAML Example

```yaml
Car:
    fields:
        color: string
        isNew: bool
        owner:
            type: Owner
            required: true

Owner:
    fields:
        name: string
```

### NEON Example

```neon
Car:
    fields:
        color: string
        isNew: bool
        owner:
            type: Owner
            required: true
```

## Framework Integration

This is the standalone core library. For framework-specific integration:

- **CakePHP**: [dereuromark/cakephp-dto](https://github.com/dereuromark/cakephp-dto) - provides CLI commands, View integration, and CakePHP Collection support

## Documentation

- [Motivation](docs/Motivation.md) - Why code generation beats runtime reflection

## License

MIT License. See [LICENSE](LICENSE) for details.
