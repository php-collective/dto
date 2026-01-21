# Collection Adapters

The DTO library supports different collection types through a pluggable adapter system. This allows framework-specific collections (CakePHP, Laravel, Doctrine) to be used without maintaining separate template files.

## Why Adapters?

Different collection implementations have different APIs:

| Collection Type | Append Method | Mutability |
|----------------|---------------|------------|
| `\ArrayObject` | `append($item)` | Mutable (modifies in place) |
| `\Cake\Collection\Collection` | `appendItem($item)` | Immutable (returns new instance) |
| `\Illuminate\Support\Collection` | `push($item)` | Mutable |
| `\Doctrine\Common\Collections\ArrayCollection` | `add($item)` | Mutable |

Without adapters, each framework would need its own template files. With adapters, we have a single set of templates that delegate to the appropriate adapter.

## Default Adapter

The library includes `ArrayObjectAdapter` as the default, which handles PHP's built-in `\ArrayObject`:

```php
use PhpCollective\Dto\Collection\ArrayObjectAdapter;

// This is the default - no registration needed
```

## Framework Integration

Framework-specific adapters should be provided by their respective wrapper packages. For example:

- **CakePHP**: `cakephp-dto` provides `CakeCollectionAdapter`
- **Laravel**: A `laravel-dto` package would provide `LaravelCollectionAdapter`

This keeps the core library framework-agnostic while allowing full customization.

## Creating Custom Adapters

Implement `CollectionAdapterInterface` for your collection type:

```php
<?php
declare(strict_types=1);

namespace App\Dto\Collection;

use PhpCollective\Dto\Collection\CollectionAdapterInterface;

class CakeCollectionAdapter implements CollectionAdapterInterface
{
    public function getCollectionClass(): string
    {
        return '\\Cake\\Collection\\Collection';
    }

    public function isImmutable(): bool
    {
        return true; // Cake collections are immutable
    }

    public function getAppendMethod(): string
    {
        return 'appendItem';
    }

    public function getCreateEmptyCode(string $typeHint): string
    {
        return "new {$typeHint}([])";
    }

    public function getAppendCode(string $collectionVar, string $itemVar): string
    {
        // Cake's appendItem() returns a new Collection instance
        return "{$collectionVar} = {$collectionVar}->appendItem({$itemVar});";
    }
}
```

Register it at bootstrap:

```php
// CakePHP plugin bootstrap
use PhpCollective\Dto\Collection\CollectionAdapterRegistry;

CollectionAdapterRegistry::register(new CakeCollectionAdapter());
```

## How It Works

During code generation, the `TwigRenderer` makes adapters available to templates:

```twig
{# In method_add.twig #}
{% set adapter = getCollectionAdapter(collectionType) %}

public function add{{ singular }}(${{ singular }}) {
    if ($this->{{ name }} === null) {
        $this->{{ name }} = {{ adapter.createEmptyCode(typeHint) }};
    }

    {{ adapter.appendCode('$this->' ~ name, '$' ~ singular) }}
    $this->_touchedFields[static::FIELD_{{ name }}] = true;

    return $this;
}
```

## Benefits

1. **Clean separation of concerns** - Core library stays framework-agnostic
2. **No phantom dependencies** - Core doesn't reference framework classes
3. **Easy extensibility** - Add support for new collection types without modifying core
4. **Framework ownership** - Each framework wrapper owns its adapter implementation
