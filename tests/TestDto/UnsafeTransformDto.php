<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

/**
 * Test DTO with an unsafe callable for security testing.
 */
class UnsafeTransformDto extends AbstractDto
{
    protected ?string $value = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'value' => [
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
            'transformFrom' => 'system', // UNSAFE - should be blocked
            'transformTo' => null,
            'isClass' => false,
            'enum' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'value' => 'value',
        ],
        'dashed' => [
            'value' => 'value',
        ],
    ];

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        $this->_touchedFields['value'] = true;

        return $this;
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
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
