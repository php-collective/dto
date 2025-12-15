<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;
use Traversable;

/**
 * A DTO with a loosely-typed Traversable field for testing custom collection cloning.
 */
class TraversableDto extends AbstractDto
{
    /**
     * @var \Traversable<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    protected ?Traversable $items = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'items' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => 'Traversable',
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
        ],
        'dashed' => [
            'items' => 'items',
        ],
    ];

    /**
     * @return \Traversable<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    public function getItems(): ?Traversable
    {
        return $this->items;
    }

    /**
     * @param \Traversable<int, \PhpCollective\Dto\Test\TestDto\SimpleDto>|null $items
     *
     * @return $this
     */
    public function setItems(?Traversable $items)
    {
        $this->items = $items;
        $this->_touchedFields['items'] = true;

        return $this;
    }

    public function hasItems(): bool
    {
        return $this->items !== null;
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
