<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use ArrayObject;
use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A DTO with associative collection fields for testing key-based collection handling.
 */
class AssociativeCollectionDto extends AbstractDto
{
    /**
     * Collection with associative=true but no specific key field.
     *
     * @var \ArrayObject<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    protected ?ArrayObject $itemsByIndex = null;

    /**
     * Collection with associative=true and key='name' field.
     *
     * @var \ArrayObject<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    protected ?ArrayObject $itemsByName = null;

    /**
     * Array collection with associative=true and key='name' field.
     *
     * @var array<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>
     */
    protected array $arrayItemsByName = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'itemsByIndex' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => ArrayObject::class,
            'singularType' => SimpleDto::class,
            'associative' => true,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
            'transformFrom' => null,
            'transformTo' => null,
        ],
        'itemsByName' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => ArrayObject::class,
            'singularType' => SimpleDto::class,
            'associative' => true,
            'key' => 'name',
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
            'transformFrom' => null,
            'transformTo' => null,
        ],
        'arrayItemsByName' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => 'array',
            'singularType' => SimpleDto::class,
            'associative' => true,
            'key' => 'name',
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
            'transformFrom' => null,
            'transformTo' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'items_by_index' => 'itemsByIndex',
            'items_by_name' => 'itemsByName',
            'array_items_by_name' => 'arrayItemsByName',
        ],
        'dashed' => [
            'items-by-index' => 'itemsByIndex',
            'items-by-name' => 'itemsByName',
            'array-items-by-name' => 'arrayItemsByName',
        ],
    ];

    /**
     * @return \ArrayObject<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    public function getItemsByIndex(): ?ArrayObject
    {
        return $this->itemsByIndex;
    }

    /**
     * @param \ArrayObject<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null $items
     *
     * @return $this
     */
    public function setItemsByIndex(?ArrayObject $items)
    {
        $this->itemsByIndex = $items;
        $this->_touchedFields['itemsByIndex'] = true;

        return $this;
    }

    /**
     * @return \ArrayObject<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    public function getItemsByName(): ?ArrayObject
    {
        return $this->itemsByName;
    }

    /**
     * @param \ArrayObject<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null $items
     *
     * @return $this
     */
    public function setItemsByName(?ArrayObject $items)
    {
        $this->itemsByName = $items;
        $this->_touchedFields['itemsByName'] = true;

        return $this;
    }

    /**
     * @return array<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>
     */
    public function getArrayItemsByName(): array
    {
        return $this->arrayItemsByName;
    }

    /**
     * @param array<string, \PhpCollective\Dto\Test\TestDto\SimpleDto> $items
     *
     * @return $this
     */
    public function setArrayItemsByName(array $items)
    {
        $this->arrayItemsByName = $items;
        $this->_touchedFields['arrayItemsByName'] = true;

        return $this;
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
