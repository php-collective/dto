<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A DTO that contains a nested DTO for testing nested functionality.
 */
class NestedDto extends AbstractDto
{
    protected ?string $title = null;

    protected ?SimpleDto $simple = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'title' => [
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
        'simple' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => true,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => true,
            'enum' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'title' => 'title',
            'simple' => 'simple',
        ],
        'dashed' => [
            'title' => 'title',
            'simple' => 'simple',
        ],
    ];

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        $this->_touchedFields['title'] = true;

        return $this;
    }

    public function hasTitle(): bool
    {
        return $this->title !== null;
    }

    public function getSimple(): ?SimpleDto
    {
        return $this->simple;
    }

    public function setSimple(?SimpleDto $simple): self
    {
        $this->simple = $simple;
        $this->_touchedFields['simple'] = true;

        return $this;
    }

    public function hasSimple(): bool
    {
        return $this->simple !== null;
    }
}
