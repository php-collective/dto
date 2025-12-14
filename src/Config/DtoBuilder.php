<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Config;

/**
 * Fluent builder for DTO configuration.
 *
 * @example
 * Dto::create('User')->fields(
 *     Field::int('id')->required(),
 *     Field::string('email')->required(),
 * )
 */
class DtoBuilder
{
    protected string $name;

    protected bool $immutable = false;

    protected ?string $extends = null;

    protected ?string $deprecated = null;

    /**
     * @var array<string>
     */
    protected array $traits = [];

    /**
     * @var array<\PhpCollective\Dto\Config\FieldBuilder>
     */
    protected array $fields = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Create a mutable DTO builder.
     */
    public static function create(string $name): static
    {
        return new static($name);
    }

    /**
     * Create an immutable DTO builder.
     */
    public static function immutable(string $name): static
    {
        $builder = new static($name);
        $builder->immutable = true;

        return $builder;
    }

    /**
     * Add fields to the DTO.
     */
    public function fields(FieldBuilder ...$fields): static
    {
        $this->fields = array_merge($this->fields, $fields);

        return $this;
    }

    /**
     * Add a single field to the DTO.
     */
    public function field(FieldBuilder $field): static
    {
        $this->fields[] = $field;

        return $this;
    }

    /**
     * Set the parent DTO to extend.
     */
    public function extends(string $parentName): static
    {
        $this->extends = $parentName;

        return $this;
    }

    /**
     * Mark this DTO as immutable.
     */
    public function asImmutable(): static
    {
        $this->immutable = true;

        return $this;
    }

    /**
     * Mark this DTO as deprecated.
     */
    public function deprecated(string $message = ''): static
    {
        $this->deprecated = $message ?: 'true';

        return $this;
    }

    /**
     * Add traits to use in this DTO.
     *
     * @param string ...$traits Fully qualified trait class names
     */
    public function traits(string ...$traits): static
    {
        $this->traits = array_merge($this->traits, $traits);

        return $this;
    }

    /**
     * Get DTO name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the DTO configuration array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $config = [];

        if ($this->immutable) {
            $config['immutable'] = true;
        }

        if ($this->extends !== null) {
            $config['extends'] = $this->extends;
        }

        if ($this->deprecated !== null) {
            $config['deprecated'] = $this->deprecated;
        }

        if ($this->traits !== []) {
            $config['traits'] = $this->traits;
        }

        $fields = [];
        foreach ($this->fields as $field) {
            $fields[$field->getName()] = $field->toArray();
        }
        $config['fields'] = $fields;

        return $config;
    }
}
