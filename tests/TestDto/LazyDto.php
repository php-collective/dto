<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use InvalidArgumentException;
use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A DTO with a lazy nested DTO field for testing lazy loading.
 */
class LazyDto extends AbstractDto
{
    /**
     * @var bool
     */
    protected const HAS_FAST_PATH = true;

    /**
     * @var string
     */
    public const FIELD_TITLE = 'title';

    /**
     * @var string
     */
    public const FIELD_NESTED = 'nested';

    /**
     * @var string
     */
    public const FIELD_ITEMS = 'items';

    protected ?string $title = null;

    protected ?SimpleDto $nested = null;

    /**
     * @var array<\PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    protected ?array $items = null;

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
            'minLength' => null,
            'maxLength' => null,
            'min' => null,
            'max' => null,
            'pattern' => null,
            'lazy' => false,
        ],
        'nested' => [
            'type' => SimpleDto::class,
            'required' => false,
            'defaultValue' => null,
            'dto' => SimpleDto::class,
            'collectionType' => null,
            'singularType' => null,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => true,
            'enum' => null,
            'minLength' => null,
            'maxLength' => null,
            'min' => null,
            'max' => null,
            'pattern' => null,
            'lazy' => true,
        ],
        'items' => [
            'type' => SimpleDto::class . '[]',
            'required' => false,
            'defaultValue' => null,
            'dto' => SimpleDto::class,
            'collectionType' => 'array',
            'singularType' => SimpleDto::class,
            'associative' => false,
            'key' => null,
            'serialize' => null,
            'factory' => null,
            'isClass' => true,
            'enum' => null,
            'minLength' => null,
            'maxLength' => null,
            'min' => null,
            'max' => null,
            'pattern' => null,
            'lazy' => true,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'title' => 'title',
            'nested' => 'nested',
            'items' => 'items',
        ],
        'dashed' => [
            'title' => 'title',
            'nested' => 'nested',
            'items' => 'items',
        ],
    ];

    /**
     * Optimized setFromArrayFast - handles lazy fields.
     *
     * @param array<string, mixed> $data
     *
     * @return void
     */
    protected function setFromArrayFast(array $data): void
    {
        if (isset($data['title'])) {
            $this->title = $data['title'];
            $this->_touchedFields[self::FIELD_TITLE] = true;
        }
        if (array_key_exists('nested', $data)) {
            $this->_lazyData['nested'] = $data['nested'];
            $this->_touchedFields[self::FIELD_NESTED] = true;
        }
        if (array_key_exists('items', $data)) {
            $this->_lazyData['items'] = $data['items'];
            $this->_touchedFields[self::FIELD_ITEMS] = true;
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateFieldNames(array $data): void
    {
        $diff = array_diff(array_keys($data), array_keys($this->_metadata));
        if ($diff) {
            throw new InvalidArgumentException('Unexpected fields: ' . implode(', ', $diff));
        }
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        $this->_touchedFields[self::FIELD_TITLE] = true;

        return $this;
    }

    public function hasTitle(): bool
    {
        return $this->title !== null;
    }

    /**
     * Lazy getter - hydrates from raw data on first access.
     */
    public function getNested(): ?SimpleDto
    {
        if (array_key_exists('nested', $this->_lazyData)) {
            $value = $this->_lazyData['nested'];
            if (is_array($value)) {
                $this->nested = new SimpleDto($value);
            } else {
                $this->nested = $value;
            }
            unset($this->_lazyData['nested']);
        }

        return $this->nested;
    }

    public function setNested(?SimpleDto $nested): self
    {
        unset($this->_lazyData['nested']);
        $this->nested = $nested;
        $this->_touchedFields[self::FIELD_NESTED] = true;

        return $this;
    }

    public function hasNested(): bool
    {
        return array_key_exists('nested', $this->_lazyData) || $this->nested !== null;
    }

    /**
     * Lazy getter - hydrates collection from raw data on first access.
     *
     * @return array<\PhpCollective\Dto\Test\TestDto\SimpleDto>|null
     */
    public function getItems(): ?array
    {
        if (array_key_exists('items', $this->_lazyData)) {
            $value = $this->_lazyData['items'];
            if (is_array($value)) {
                $this->items = [];
                foreach ($value as $k => $v) {
                    $this->items[$k] = is_array($v) ? new SimpleDto($v) : $v;
                }
            } else {
                $this->items = $value;
            }
            unset($this->_lazyData['items']);
        }

        return $this->items;
    }

    /**
     * @param array<\PhpCollective\Dto\Test\TestDto\SimpleDto>|null $items
     *
     * @return $this
     */
    public function setItems(?array $items)
    {
        unset($this->_lazyData['items']);
        $this->items = $items;
        $this->_touchedFields[self::FIELD_ITEMS] = true;

        return $this;
    }

    public function hasItems(): bool
    {
        return array_key_exists('items', $this->_lazyData) || $this->items !== null;
    }

    /**
     * Optimized toArray.
     *
     * @param string|null $type
     * @param array<string>|null $fields
     * @param bool $touched
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $type = null, ?array $fields = null, bool $touched = false): array
    {
        if ($type === null && $fields === null && !$touched) {
            return $this->toArrayFast();
        }

        return $this->_toArrayInternal($type, $fields, $touched);
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArrayFast(): array
    {
        $result = [];
        $result['title'] = $this->title;

        if (array_key_exists('nested', $this->_lazyData)) {
            $result['nested'] = $this->_lazyData['nested'];
        } else {
            $result['nested'] = $this->nested !== null ? $this->nested->toArray() : null;
        }

        if (array_key_exists('items', $this->_lazyData)) {
            $result['items'] = $this->_lazyData['items'];
        } else {
            if ($this->items !== null) {
                $result['items'] = [];
                foreach ($this->items as $k => $v) {
                    $result['items'][$k] = $v->toArray();
                }
            } else {
                $result['items'] = null;
            }
        }

        return $result;
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
        return new static($data, $ignoreMissing, $type);
    }
}
