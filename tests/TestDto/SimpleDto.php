<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A simple DTO for testing basic functionality.
 */
class SimpleDto extends AbstractDto
{
    protected ?string $name = null;

    protected ?int $count = null;

    protected ?bool $active = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'name' => [
            'type' => 'string',
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
        ],
        'count' => [
            'type' => 'int',
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
        ],
        'active' => [
            'type' => 'bool',
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => null,
            'singularType' => null,
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
            'name' => 'name',
            'count' => 'count',
            'active' => 'active',
        ],
        'dashed' => [
            'name' => 'name',
            'count' => 'count',
            'active' => 'active',
        ],
    ];

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        $this->_touchedFields['name'] = true;

        return $this;
    }

    public function hasName(): bool
    {
        return $this->name !== null;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(?int $count): self
    {
        $this->count = $count;
        $this->_touchedFields['count'] = true;

        return $this;
    }

    public function hasCount(): bool
    {
        return $this->count !== null;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;
        $this->_touchedFields['active'] = true;

        return $this;
    }

    public function hasActive(): bool
    {
        return $this->active !== null;
    }
}
