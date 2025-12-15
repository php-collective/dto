<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;
use PhpCollective\Dto\Test\Generator\Fixtures\FromArrayToArrayClass;
use PhpCollective\Dto\Test\Generator\Fixtures\StringableClass;
use PhpCollective\Dto\Test\Generator\Fixtures\ToArrayClass;
use PhpCollective\Dto\Test\Generator\Fixtures\UnitEnum;

/**
 * A DTO with serializable class fields and enum fields for testing.
 */
class SerializableDto extends AbstractDto
{
    protected ?FromArrayToArrayClass $fromArrayData = null;

    protected ?ToArrayClass $toArrayData = null;

    protected ?StringableClass $stringData = null;

    protected ?UnitEnum $status = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'fromArrayData' => [
            'type' => FromArrayToArrayClass::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => 'FromArrayToArray',
            'factory' => null,
            'isClass' => true,
            'enum' => null,
        ],
        'toArrayData' => [
            'type' => ToArrayClass::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => 'array',
            'factory' => null,
            'isClass' => true,
            'enum' => null,
        ],
        'stringData' => [
            'type' => StringableClass::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => 'string',
            'factory' => null,
            'isClass' => true,
            'enum' => null,
        ],
        'status' => [
            'type' => UnitEnum::class,
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
            'enum' => 'unit',
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'from_array_data' => 'fromArrayData',
            'to_array_data' => 'toArrayData',
            'string_data' => 'stringData',
            'status' => 'status',
        ],
        'dashed' => [
            'from-array-data' => 'fromArrayData',
            'to-array-data' => 'toArrayData',
            'string-data' => 'stringData',
            'status' => 'status',
        ],
    ];

    public function getFromArrayData(): ?FromArrayToArrayClass
    {
        return $this->fromArrayData;
    }

    public function setFromArrayData(?FromArrayToArrayClass $fromArrayData): self
    {
        $this->fromArrayData = $fromArrayData;
        $this->_touchedFields['fromArrayData'] = true;

        return $this;
    }

    public function hasFromArrayData(): bool
    {
        return $this->fromArrayData !== null;
    }

    public function getToArrayData(): ?ToArrayClass
    {
        return $this->toArrayData;
    }

    public function setToArrayData(?ToArrayClass $toArrayData): self
    {
        $this->toArrayData = $toArrayData;
        $this->_touchedFields['toArrayData'] = true;

        return $this;
    }

    public function hasToArrayData(): bool
    {
        return $this->toArrayData !== null;
    }

    public function getStringData(): ?StringableClass
    {
        return $this->stringData;
    }

    public function setStringData(?StringableClass $stringData): self
    {
        $this->stringData = $stringData;
        $this->_touchedFields['stringData'] = true;

        return $this;
    }

    public function hasStringData(): bool
    {
        return $this->stringData !== null;
    }

    public function getStatus(): ?UnitEnum
    {
        return $this->status;
    }

    public function setStatus(?UnitEnum $status): self
    {
        $this->status = $status;
        $this->_touchedFields['status'] = true;

        return $this;
    }

    public function hasStatus(): bool
    {
        return $this->status !== null;
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
