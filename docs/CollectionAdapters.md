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

## Built-in Adapters

### ArrayObjectAdapter (Default)

```php
use PhpCollective\Dto\Collection\ArrayObjectAdapter;

// This is the default - no registration needed
```

### CakeCollectionAdapter

```php
use PhpCollective\Dto\Collection\CakeCollectionAdapter;
use PhpCollective\Dto\Collection\CollectionAdapterRegistry;

// In CakePHP plugin bootstrap
CollectionAdapterRegistry::register(new CakeCollectionAdapter());
```

## Creating Custom Adapters

Implement `CollectionAdapterInterface` for your collection type:

```php
<?php
declare(strict_types=1);

namespace App\Dto\Collection;

use PhpCollective\Dto\Collection\CollectionAdapterInterface;

class LaravelCollectionAdapter implements CollectionAdapterInterface
{
    public function getCollectionClass(): string
    {
        return '\\Illuminate\\Support\\Collection';
    }

    public function isImmutable(): bool
    {
        return false; // Laravel collections are mutable
    }

    public function getAppendMethod(): string
    {
        return 'push';
    }

    public function getCreateEmptyCode(string $typeHint): string
    {
        return "collect([])"; // Laravel helper
    }

    public function getAppendCode(string $collectionVar, string $itemVar): string
    {
        return "{$collectionVar}->push({$itemVar});";
    }
}
```

Register it in your service provider:

```php
// AppServiceProvider.php
public function boot(): void
{
    CollectionAdapterRegistry::register(new LaravelCollectionAdapter());
}
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

1. **Single source of truth** - One set of templates for all frameworks
2. **No template duplication** - Framework wrappers don't need their own templates
3. **Easy extensibility** - Add support for new collection types without modifying core
4. **Type safety** - Adapters ensure correct method calls for each collection type

## Migration for cakephp-dto

With collection adapters, `cakephp-dto` can be simplified to:

```php
// CakeDtoPlugin.php
public function bootstrap(PluginApplicationInterface $app): void
{
    // Register CakePHP collection adapter
    CollectionAdapterRegistry::register(new CakeCollectionAdapter());

    // Set collection factory (existing functionality)
    Dto::setCollectionFactory(fn ($items) => new Collection($items));
}
```

The CakePHP-specific templates (`method_add_cake_collection.twig`, `method_with_added_cake_collection.twig`) can be removed, and the plugin becomes a thin integration layer.
