<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use ArrayObject;
use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A DTO with collection fields for testing collection handling.
 */
class CollectionDto extends AbstractDto
{
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
     * @return $this
     */
    public function setItems(?ArrayObject $items)
    {
        $this->items = $items;
        $this->_touchedFields['items'] = true;

        return $this;
    }

    /**
     * @param \PhpCollective\Dto\Test\TestDto\SimpleDto $item
     *
     * @return $this
     */
    public function addItem(SimpleDto $item)
    {
        if ($this->items === null) {
            $this->items = new ArrayObject();
        }
        $this->items->append($item);
        $this->_touchedFields['items'] = true;

        return $this;
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
     * @return $this
     */
    public function setArrayItems(array $arrayItems)
    {
        $this->arrayItems = $arrayItems;
        $this->_touchedFields['arrayItems'] = true;

        return $this;
    }

    /**
     * @param \PhpCollective\Dto\Test\TestDto\SimpleDto $item
     *
     * @return $this
     */
    public function addArrayItem(SimpleDto $item)
    {
        $this->arrayItems[] = $item;
        $this->_touchedFields['arrayItems'] = true;

        return $this;
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
