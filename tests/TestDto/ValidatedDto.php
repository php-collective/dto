<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractDto;

/**
 * A DTO with validation rules for testing.
 */
class ValidatedDto extends AbstractDto
{
    /**
     * @var string
     */
    public const FIELD_NAME = 'name';

    /**
     * @var string
     */
    public const FIELD_EMAIL = 'email';

    /**
     * @var string
     */
    public const FIELD_AGE = 'age';

    /**
     * @var string
     */
    public const FIELD_SCORE = 'score';

    protected ?string $name = null;

    protected ?string $email = null;

    protected ?int $age = null;

    protected ?float $score = null;

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
            'minLength' => 2,
            'maxLength' => 50,
            'min' => null,
            'max' => null,
            'pattern' => null,
        ],
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
            'isClass' => false,
            'enum' => null,
            'minLength' => null,
            'maxLength' => null,
            'min' => null,
            'max' => null,
            'pattern' => '/^[^@]+@[^@]+$/',
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
            'min' => 0,
            'max' => 150,
            'pattern' => null,
        ],
        'score' => [
            'type' => 'float',
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
            'min' => 0.0,
            'max' => 100.0,
            'pattern' => null,
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'name' => 'name',
            'email' => 'email',
            'age' => 'age',
            'score' => 'score',
        ],
        'dashed' => [
            'name' => 'name',
            'email' => 'email',
            'age' => 'age',
            'score' => 'score',
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

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): self
    {
        $this->age = $age;
        $this->_touchedFields['age'] = true;

        return $this;
    }

    public function hasAge(): bool
    {
        return $this->age !== null;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(?float $score): self
    {
        $this->score = $score;
        $this->_touchedFields['score'] = true;

        return $this;
    }

    public function hasScore(): bool
    {
        return $this->score !== null;
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
