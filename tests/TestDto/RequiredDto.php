<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A DTO with required fields for testing validation.
 */
class RequiredDto extends AbstractDto
{
    protected ?string $name = null;

    protected ?string $email = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'name' => [
            'type' => 'string',
            'required' => true,
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
        'email' => [
            'type' => 'string',
            'required' => true,
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
            'email' => 'email',
        ],
        'dashed' => [
            'name' => 'name',
            'email' => 'email',
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
}
