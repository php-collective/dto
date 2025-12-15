<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A DTO with a custom collection type (not ArrayObject) for testing collection factory.
 */
class CustomCollectionDto extends AbstractDto
{
    /**
     * @var \PhpCollective\Dto\Test\TestDto\TestCollection<\PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    protected ?TestCollection $items = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'items' => [
            'type' => SimpleDto::class . '[]',
            'required' => false,
            'defaultValue' => null,
            'dto' => false,
            'collectionType' => TestCollection::class,
            'singularType' => SimpleDto::class,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => false,
            'enum' => null,
            'mapFrom' => null,
            'mapTo' => null,
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
     * @return \PhpCollective\Dto\Test\TestDto\TestCollection<\PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    public function getItems(): ?TestCollection
    {
        return $this->items;
    }

    /**
     * @param \PhpCollective\Dto\Test\TestDto\TestCollection<\PhpCollective\Dto\Test\TestDto\SimpleDto>|null $items
     *
     * @return $this
     */
    public function setItems(?TestCollection $items)
    {
        $this->items = $items;
        $this->_touchedFields['items'] = true;

        return $this;
    }

    /**
     * @return bool
     */
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
