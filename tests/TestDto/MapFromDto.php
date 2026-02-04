<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

/**
 * Test DTO for mapFrom + key type inflection interaction.
 */
class MapFromDto extends AbstractDto
{
    protected ?string $emailAddress = null;

    protected ?string $firstName = null;

    protected ?int $userId = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [
        'emailAddress' => [
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
            'transformFrom' => null,
            'transformTo' => null,
            'isClass' => false,
            'enum' => null,
            'mapFrom' => 'email',
            'mapTo' => null,
        ],
        'firstName' => [
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
            'transformFrom' => null,
            'transformTo' => null,
            'isClass' => false,
            'enum' => null,
            'mapFrom' => 'first_name',
            'mapTo' => null,
        ],
        'userId' => [
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
            'transformFrom' => null,
            'transformTo' => null,
            'isClass' => false,
            'enum' => null,
            'mapFrom' => 'user-id',
            'mapTo' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'email_address' => 'emailAddress',
            'first_name' => 'firstName',
            'user_id' => 'userId',
        ],
        'dashed' => [
            'email-address' => 'emailAddress',
            'first-name' => 'firstName',
            'user-id' => 'userId',
        ],
    ];

    /**
     * Custom field mapping for input/output name translation.
     *
     * @var array<string, array<string, string>>
     */
    protected array $_fieldMap = [
        'mapFrom' => [
            'email' => 'emailAddress',
            'first_name' => 'firstName',
            'user-id' => 'userId',
        ],
    ];

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(?string $emailAddress): self
    {
        $this->emailAddress = $emailAddress;
        $this->_touchedFields['emailAddress'] = true;

        return $this;
    }

    public function hasEmailAddress(): bool
    {
        return $this->emailAddress !== null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        $this->_touchedFields['firstName'] = true;

        return $this;
    }

    public function hasFirstName(): bool
    {
        return $this->firstName !== null;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        $this->_touchedFields['userId'] = true;

        return $this;
    }

    public function hasUserId(): bool
    {
        return $this->userId !== null;
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
