# PHP Data Transfer Objects

[![CI](https://github.com/php-collective/dto/actions/workflows/ci.yml/badge.svg)](https://github.com/php-collective/dto/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/php-collective/dto/branch/master/graph/badge.svg)](https://codecov.io/gh/php-collective/dto)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://phpstan.org/)
[![Latest Stable Version](https://poser.pugx.org/php-collective/dto/v/stable.svg)](https://packagist.org/packages/dereuromark/cakephp-ide-helper)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Framework-agnostic DTO library with **code generation** for PHP.

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

Define DTOs in PHP (or XML, YAML, NEON):

```php
// config/dto.php
use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;
use PhpCollective\Dto\Config\Schema;

return Schema::create()
    ->dto(Dto::create('Car')->fields(
        Field::string('color'),
        Field::dto('owner', 'Owner'),
    ))
    ->dto(Dto::create('Owner')->fields(
        Field::string('name'),
    ))
    ->toArray();
```

Generate and use:

```bash
vendor/bin/dto generate
```

```php
$car = CarDto::createFromArray(['color' => 'red']);
$car->setOwner(OwnerDto::create(['name' => 'John']));
$array = $car->toArray();
```

See [Quick Start Guide](docs/README.md) for detailed examples.

## Immutable DTOs

A more realistic example using immutable DTOs for a blog system:

```php
// config/dto.php
return Schema::create()
    ->dto(Dto::create('Article')->immutable()->fields(
        Field::int('id')->required(),
        Field::string('title')->required(),
        Field::string('slug')->required(),
        Field::string('content'),
        Field::dto('author', 'Author')->required(),
        Field::collection('tags', 'Tag')->singular('tag'),
        Field::bool('published')->defaultValue(false),
        Field::string('publishedAt'),
    ))
    ->dto(Dto::create('Author')->immutable()->fields(
        Field::string('name')->required(),
        Field::string('email'),
        Field::string('avatarUrl'),
    ))
    ->dto(Dto::create('Tag')->immutable()->fields(
        Field::string('name')->required(),
        Field::string('slug')->required(),
    ))
    ->toArray();
```

Immutable DTOs use `with*()` methods that return new instances:

```php
// Creating from API/database response
$article = ArticleDto::createFromArray($apiResponse);

// Modifications return new instances (original unchanged)
$published = $article
    ->withPublished(true)
    ->withPublishedAt(date('Y-m-d H:i:s'));
```

Reading in a template (e.g., Twig, Blade, or plain PHP):

```php
<!-- templates/article/view.php -->
<article>
    <h1><?= htmlspecialchars($article->getTitle()) ?></h1>
    <p class="meta">
        By <?= htmlspecialchars($article->getAuthor()->getName()) ?>
        <?php if ($article->getPublishedAt()) { ?>
            on <?= $article->getPublishedAt() ?>
        <?php } ?>
    </p>

    <div class="tags">
        <?php foreach ($article->getTags() as $tag) { ?>
            <a href="/tag/<?= $tag->getSlug() ?>"><?= htmlspecialchars($tag->getName()) ?></a>
        <?php } ?>
    </div>

    <div class="content">
        <?= $article->getContent() ?>
    </div>
</article>
```

## Features

- **Types**: `int`, `float`, `string`, `bool`, `array`, `mixed`, DTOs, classes, enums
- **Union types**: `int|string`, `int|float|string` (PHP 8.0+)
- **Collections**: `'type' => 'Item[]', 'collection' => true` with add/get/has methods
- **Associative collections**: keyed access with `'associative' => true`
- **Immutable DTOs**: `'immutable' => true` with `with*()` methods
- **Default values**: `'defaultValue' => 0`
- **Required fields**: `'required' => true`
- **Deprecations**: `'deprecated' => 'Use newField instead'`
- **Inflection**: automatic snake_case/camelCase/dash-case conversion
- **Deep cloning**: `$dto->clone()`
- **Nested reading**: `$dto->read(['path', 'to', 'field'])`
- **PHPDoc generics**: `@var \ArrayObject<int, ItemDto>` for static analysis

## Configuration Formats

- **PHP** - native arrays or [fluent builder](docs/ConfigBuilder.md)
- **XML** - XSD validation, IDE autocomplete
- **YAML** - requires `pecl install yaml`
- **NEON** - requires `nette/neon`

## Documentation

- [Quick Start Guide](docs/README.md) - Getting started with examples
- [Configuration Builder](docs/ConfigBuilder.md) - Fluent API for defining DTOs
- [Examples](docs/Examples.md) - Practical usage patterns
- [Motivation](docs/Motivation.md) - Why code generation beats runtime reflection

## Integrations

This is the standalone core library. For framework-specific integrations:

- **CakePHP**: [dereuromark/cakephp-dto](https://github.com/dereuromark/cakephp-dto)
