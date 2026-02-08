<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use InvalidArgumentException;
use PhpCollective\Dto\Dto\AbstractImmutableDto;

/**
 * An immutable DTO using public readonly properties.
 */
class ReadonlyDto extends AbstractImmutableDto
{
    /**
     * @var bool
     */
    protected const HAS_FAST_PATH = true;

    /**
     * @var string
     */
    public const FIELD_NAME = 'name';

    /**
     * @var string
     */
    public const FIELD_AGE = 'age';

    /**
     * @var string
     */
    public const FIELD_ACTIVE = 'active';

    public readonly ?string $name;

    public readonly ?int $age;

    public readonly ?bool $active;

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
            'minLength' => null,
            'maxLength' => null,
            'min' => null,
            'max' => null,
            'pattern' => null,
            'lazy' => false,
        ],
        'age' => [
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
            'minLength' => null,
            'maxLength' => null,
            'min' => null,
            'max' => null,
            'pattern' => null,
            'lazy' => false,
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
            'minLength' => null,
            'maxLength' => null,
            'min' => null,
            'max' => null,
            'pattern' => null,
            'lazy' => false,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'name' => 'name',
            'age' => 'age',
            'active' => 'active',
        ],
        'dashed' => [
            'name' => 'name',
            'age' => 'age',
            'active' => 'active',
        ],
    ];

    /**
     * Optimized setFromArrayFast - direct assignment during constructor.
     *
     * @param array<string, mixed> $data
     *
     * @return void
     */
    protected function setFromArrayFast(array $data): void
    {
        if (isset($data['name'])) {
            $this->name = $data['name'];
            $this->_touchedFields[self::FIELD_NAME] = true;
        }
        if (isset($data['age'])) {
            $this->age = (int)$data['age'];
            $this->_touchedFields[self::FIELD_AGE] = true;
        }
        if (isset($data['active'])) {
            $this->active = (bool)$data['active'];
            $this->_touchedFields[self::FIELD_ACTIVE] = true;
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

    /**
     * Initialize unset nullable readonly properties to null.
     *
     * @return $this
     */
    protected function setDefaults()
    {
        if (!isset($this->name)) {
            $this->name = null;
        }
        if (!isset($this->age)) {
            $this->age = null;
        }
        if (!isset($this->active)) {
            $this->active = null;
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArrayFast(): array
    {
        return [
            'name' => $this->name,
            'age' => $this->age,
            'active' => $this->active,
        ];
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
        if ($type === null && $fields === null && !$touched) {
            return $this->toArrayFast();
        }

        return $this->_toArrayInternal($type, $fields, $touched);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return static
     */
    public function withName(?string $name)
    {
        $data = $this->toArray();
        $data['name'] = $name;

        return new static($data);
    }

    public function hasName(): bool
    {
        return $this->name !== null;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    /**
     * @return static
     */
    public function withAge(?int $age)
    {
        $data = $this->toArray();
        $data['age'] = $age;

        return new static($data);
    }

    public function hasAge(): bool
    {
        return $this->age !== null;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    /**
     * @return static
     */
    public function withActive(?bool $active)
    {
        $data = $this->toArray();
        $data['active'] = $active;

        return new static($data);
    }

    public function hasActive(): bool
    {
        return $this->active !== null;
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
