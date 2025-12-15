<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;
use PhpCollective\Dto\Test\Generator\Fixtures\FactoryClass;
use PhpCollective\Dto\Test\Generator\Fixtures\IntBackedEnum;
use PhpCollective\Dto\Test\Generator\Fixtures\PlainClass;

/**
 * An advanced DTO for testing factory, backed enums, and class constructors.
 */
class AdvancedDto extends AbstractDto
{
    protected ?FactoryClass $factoryData = null;

    protected ?IntBackedEnum $priority = null;

    protected ?PlainClass $plainData = null;

    /**
     * @var array<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>
     */
    protected array $associativeItems = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'factoryData' => [
            'type' => FactoryClass::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => 'create',
            'isClass' => true,
            'enum' => null,
        ],
        'priority' => [
            'type' => IntBackedEnum::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => true,
            'enum' => 'int',
        ],
        'plainData' => [
            'type' => PlainClass::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => true,
            'enum' => null,
        ],
        'associativeItems' => [
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
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'factory_data' => 'factoryData',
            'priority' => 'priority',
            'plain_data' => 'plainData',
            'associative_items' => 'associativeItems',
        ],
        'dashed' => [
            'factory-data' => 'factoryData',
            'priority' => 'priority',
            'plain-data' => 'plainData',
            'associative-items' => 'associativeItems',
        ],
    ];

    public function getFactoryData(): ?FactoryClass
    {
        return $this->factoryData;
    }

    public function setFactoryData(?FactoryClass $factoryData): self
    {
        $this->factoryData = $factoryData;
        $this->_touchedFields['factoryData'] = true;

        return $this;
    }

    public function hasFactoryData(): bool
    {
        return $this->factoryData !== null;
    }

    public function getPriority(): ?IntBackedEnum
    {
        return $this->priority;
    }

    public function setPriority(?IntBackedEnum $priority): self
    {
        $this->priority = $priority;
        $this->_touchedFields['priority'] = true;

        return $this;
    }

    public function hasPriority(): bool
    {
        return $this->priority !== null;
    }

    public function getPlainData(): ?PlainClass
    {
        return $this->plainData;
    }

    public function setPlainData(?PlainClass $plainData): self
    {
        $this->plainData = $plainData;
        $this->_touchedFields['plainData'] = true;

        return $this;
    }

    public function hasPlainData(): bool
    {
        return $this->plainData !== null;
    }

    /**
     * @return array<string, \PhpCollective\Dto\Test\TestDto\SimpleDto>
     */
    public function getAssociativeItems(): array
    {
        return $this->associativeItems;
    }

    /**
     * @param array<string, \PhpCollective\Dto\Test\TestDto\SimpleDto> $associativeItems
     *
     * @return $this
     */
    public function setAssociativeItems(array $associativeItems)
    {
        $this->associativeItems = $associativeItems;
        $this->_touchedFields['associativeItems'] = true;

        return $this;
    }

    public function hasAssociativeItems(): bool
    {
        return count($this->associativeItems) > 0;
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
