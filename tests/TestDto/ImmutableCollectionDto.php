<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use ArrayObject;
use PhpCollective\Dto\Dto\AbstractImmutableDto;

/**
 * An immutable DTO with collection fields for testing immutable collection handling.
 */
class ImmutableCollectionDto extends AbstractImmutableDto
{
    /**
     * @var bool
     */
    protected const IS_IMMUTABLE = true;

    /**
     * @var \ArrayObject<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    protected ?ArrayObject $items = null;

    /**
     * @var array<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>
     */
    protected array $arrayItems = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'items' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => ArrayObject::class,
            'singularType' => SimpleDto::class,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
        ],
        'arrayItems' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => 'array',
            'singularType' => SimpleDto::class,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'items' => 'items',
            'array_items' => 'arrayItems',
        ],
        'dashed' => [
            'items' => 'items',
            'array-items' => 'arrayItems',
        ],
    ];

    /**
     * @return \ArrayObject<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    public function getItems(): ?ArrayObject
    {
        return $this->items;
    }

    /**
     * @param \ArrayObject<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null $items
     *
     * @return static
     */
    public function withItems(?ArrayObject $items): static
    {
        $new = clone $this;
        $new->items = $items;
        $new->_touchedFields['items'] = true;

        return $new;
    }

    /**
     * @param \PhpCollective\Dto\Test\TestDto\SimpleDto $item
     *
     * @return static
     */
    public function withAddedItem(SimpleDto $item): static
    {
        $new = clone $this;

        if ($new->items === null) {
            $new->items = new ArrayObject();
        } else {
            // Deep clone the ArrayObject to avoid mutating original
            $new->items = clone $new->items;
        }

        $new->items->append($item);
        $new->_touchedFields['items'] = true;

        return $new;
    }

    /**
     * @param string|int $key
     *
     * @return static
     */
    public function withRemovedItem($key): static
    {
        $new = clone $this;

        if ($new->items === null) {
            return $new;
        }

        // Deep clone the ArrayObject to avoid mutating original
        $new->items = clone $new->items;

        if ($new->items->offsetExists($key)) {
            $new->items->offsetUnset($key);
        }
        $new->_touchedFields['items'] = true;

        return $new;
    }

    public function hasItems(): bool
    {
        return $this->items !== null && $this->items->count() > 0;
    }

    /**
     * @return array<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>
     */
    public function getArrayItems(): array
    {
        return $this->arrayItems;
    }

    /**
     * @param array<int, \PhpCollective\Dto\Test\TestDto\SimpleDto> $arrayItems
     *
     * @return static
     */
    public function withArrayItems(array $arrayItems): static
    {
        $new = clone $this;
        $new->arrayItems = $arrayItems;
        $new->_touchedFields['arrayItems'] = true;

        return $new;
    }

    /**
     * @param \PhpCollective\Dto\Test\TestDto\SimpleDto $item
     *
     * @return static
     */
    public function withAddedArrayItem(SimpleDto $item): static
    {
        $new = clone $this;
        $new->arrayItems[] = $item;
        $new->_touchedFields['arrayItems'] = true;

        return $new;
    }

    /**
     * @param string|int $key
     *
     * @return static
     */
    public function withRemovedArrayItem($key): static
    {
        $new = clone $this;
        unset($new->arrayItems[$key]);
        $new->_touchedFields['arrayItems'] = true;

        return $new;
    }

    public function hasArrayItems(): bool
    {
        return count($this->arrayItems) > 0;
    }

    /**
     * @param string|null $type
     * @param array<string>|null $fields
     * @param bool $touched
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $type = null, ?array $fields = null, bool $touched = false): array
    {
        return $this->_toArrayInternal($type, $fields, $touched);
    }

    /**
     * @param array<string, mixed> $data
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return static
     */
    public static function createFromArray(array $data, bool $ignoreMissing = false, ?string $type = null): static
    {
        return static::_createFromArrayInternal($data, $ignoreMissing, $type);
    }
}
