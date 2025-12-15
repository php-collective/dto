# Quick Start Guide

**Documentation:**
[Configuration Builder](ConfigBuilder.md) |
[Examples](Examples.md) |
[Advanced Patterns](AdvancedPatterns.md) |
[Validation](Validation.md) |
[CLI Reference](CliReference.md) |
[Testing](Testing.md) |
[Troubleshooting](Troubleshooting.md) |
[Performance](Performance.md) |
[Migration](Migration.md) |
[TypeScript Generation](TypeScriptGeneration.md) |
[Shaped Array Types](ShapedArrayTypes.md) |
[Motivation](Motivation.md)

---

## 1. Define DTOs

Create a `dto.xml` configuration file in your `config/` directory:

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

## 2. Generate DTOs

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

## Deployment Strategies

There are two approaches to deploying generated DTOs:

### Commit DTOs to Version Control (Recommended)

Generate DTOs locally, commit them to your repository, and deploy like any other code:

```bash
composer require --dev php-collective/dto
```

**Benefits:**
- DTOs are reviewed in pull requests
- No generation step needed on production servers
- Faster deployments
- Works with read-only filesystems

**Workflow:**
1. Modify DTO configuration
2. Run `vendor/bin/dto generate`
3. Commit generated files
4. Deploy as usual

### Runtime Generation on Server

Generate DTOs as part of your deployment process:

```bash
composer require php-collective/dto
```

**When to use:**
- Dynamic DTO definitions
- Environments where committing generated code isn't desired

**Note:** This requires the library in production and write access to the source directory during deployment.

### Exclude Generated DTOs from Static Analysis

Generated DTOs should be excluded from phpcs, phpstan, and similar tools:

**phpcs.xml:**
```xml
<exclude-pattern>src/Dto/*</exclude-pattern>
```

**phpstan.neon:**
```neon
parameters:
    excludePaths:
        - src/Dto/*
```

## 3. Use Generated DTOs

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

See [Configuration Builder](ConfigBuilder.md) for full documentation.
