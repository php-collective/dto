<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use PhpCollective\Dto\Dto\AbstractImmutableDto;

/**
 * An immutable DTO for testing immutability functionality.
 */
class ImmutableDto extends AbstractImmutableDto
{
    /**
     * @var bool
     */
    protected const IS_IMMUTABLE = true;

    protected ?string $title = null;

    protected ?int $version = null;

    protected ?bool $published = null;

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
        'version' => [
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
        ],
        'published' => [
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
        ],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [
        'underscored' => [
            'title' => 'title',
            'version' => 'version',
            'published' => 'published',
        ],
        'dashed' => [
            'title' => 'title',
            'version' => 'version',
            'published' => 'published',
        ],
    ];

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function withTitle(?string $title): static
    {
        $new = clone $this;
        $new->title = $title;
        $new->_touchedFields['title'] = true;

        return $new;
    }

    public function hasTitle(): bool
    {
        return $this->title !== null;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function withVersion(?int $version): static
    {
        $new = clone $this;
        $new->version = $version;
        $new->_touchedFields['version'] = true;

        return $new;
    }

    public function hasVersion(): bool
    {
        return $this->version !== null;
    }

    public function getPublished(): ?bool
    {
        return $this->published;
    }

    public function withPublished(?bool $published): static
    {
        $new = clone $this;
        $new->published = $published;
        $new->_touchedFields['published'] = true;

        return $new;
    }

    public function hasPublished(): bool
    {
        return $this->published !== null;
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
