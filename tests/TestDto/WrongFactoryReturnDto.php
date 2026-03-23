<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;
use PhpCollective\Dto\Test\Generator\Fixtures\FactoryClass;

class WrongFactoryReturnDto extends AbstractDto
{
    protected ?FactoryClass $factoryData = null;

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
            'factory' => WrongFactoryReturnValue::class . '::create',
            'isClass' => true,
            'enum' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'factory_data' => 'factoryData',
        ],
        'dashed' => [
            'factory-data' => 'factoryData',
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

    public function toArray(?string $type = null, ?array $fields = null, bool $touched = false): array
    {
        return $this->_toArrayInternal($type, $fields, $touched);
    }

    public static function createFromArray(array $data, bool $ignoreMissing = false, ?string $type = null): static
    {
        return static::_createFromArrayInternal($data, $ignoreMissing, $type);
    }
}
