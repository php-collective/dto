<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

class TransformDto extends AbstractDto
{
    protected ?string $email = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'email' => [
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
            'transformFrom' => TransformHelper::class . '::normalizeEmail',
            'transformTo' => TransformHelper::class . '::maskEmail',
            'isClass' => false,
            'enum' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'email' => 'email',
        ],
        'dashed' => [
            'email' => 'email',
        ],
    ];

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        $this->_touchedFields['email'] = true;

        return $this;
    }

    public function hasEmail(): bool
    {
        return $this->email !== null;
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
