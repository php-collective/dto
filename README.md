# PHP DTO

[![CI](https://github.com/php-collective/dto/actions/workflows/ci.yml/badge.svg)](https://github.com/php-collective/dto/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/php-collective/dto/branch/master/graph/badge.svg)](https://codecov.io/gh/php-collective/dto)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Framework-agnostic Data Transfer Object library with code generation for PHP.

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

## Features

- Type-safe DTOs with immutable variants
- Code generation from XML/YAML/NEON configuration
- Twig-based template rendering
- Collection support with pluggable adapters
- Nested DTO support
- Deprecation tracking

## Usage

### Define DTOs in XML

Create a `dto.xml` configuration file:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="https://github.com/php-collective/dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
        <field name="email" type="string"/>
    </dto>
</dtos>
```

### Generate DTOs

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

### Use Generated DTOs

```php
use App\Dto\UserDto;

$user = new UserDto();
$user->setName('John Doe');
$user->setEmail('john@example.com');

// Or create from array
$user = UserDto::createFromArray([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Convert back to array
$array = $user->toArray();
```

## Collection Support

By default, collections are returned as arrays. You can inject a custom collection factory:

```php
use PhpCollective\Dto\Dto\Dto;

// For CakePHP
Dto::setCollectionFactory(fn($items) => new \Cake\Collection\Collection($items));

// For Laravel
Dto::setCollectionFactory(fn($items) => collect($items));
```

## Configuration Formats

The library supports multiple configuration formats:

- **XML** (default) - with XSD validation
- **YAML** - requires `symfony/yaml` (included via Twig)
- **NEON** - requires `nette/neon`

## License

MIT License. See [LICENSE](LICENSE) for details.
