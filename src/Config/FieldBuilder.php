<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Config;

use InvalidArgumentException;

/**
 * Fluent builder for DTO field configuration.
 *
 * @example
 * Field::string('email')->required()
 * Field::int('id')->required()
 * Field::dto('address', 'Address')
 * Field::collection('items', 'Item')->singular('item')
 */
class FieldBuilder
{
    protected string $name;

    protected string $type;

    protected bool $required = false;

    protected mixed $defaultValue = null;

    protected bool $hasDefaultValue = false;

    protected bool $collection = false;

    protected ?string $collectionType = null;

    protected ?string $singular = null;

    protected bool $associative = false;

    protected ?string $key = null;

    protected ?string $deprecated = null;

    protected ?string $factory = null;

    protected ?string $serialize = null;

    protected ?string $mapFrom = null;

    protected ?string $mapTo = null;

    protected ?string $transformFrom = null;

    protected ?string $transformTo = null;

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    /**
     * Create a string field.
     */
    public static function string(string $name): static
    {
        return new static($name, 'string');
    }

    /**
     * Create an integer field.
     */
    public static function int(string $name): static
    {
        return new static($name, 'int');
    }

    /**
     * Create a float field.
     */
    public static function float(string $name): static
    {
        return new static($name, 'float');
    }

    /**
     * Create a boolean field.
     */
    public static function bool(string $name): static
    {
        return new static($name, 'bool');
    }

    /**
     * Create an array field.
     */
    public static function array(string $name, ?string $elementType = null): static
    {
        $type = $elementType ? $elementType . '[]' : 'array';

        return new static($name, $type);
    }

    /**
     * Create a field referencing another DTO.
     */
    public static function dto(string $name, string $dtoName): static
    {
        return new static($name, $dtoName);
    }

    /**
     * Create a collection field.
     */
    public static function collection(string $name, string $elementType): static
    {
        $field = new static($name, $elementType . '[]');
        $field->collection = true;

        return $field;
    }

    /**
     * Create a field with a class type (e.g., DateTimeImmutable).
     */
    public static function class(string $name, string $className): static
    {
        // Ensure FQCN starts with backslash
        if (!str_starts_with($className, '\\')) {
            $className = '\\' . $className;
        }

        return new static($name, $className);
    }

    /**
     * Create an enum field.
     */
    public static function enum(string $name, string $enumClass): static
    {
        if (!str_starts_with($enumClass, '\\')) {
            $enumClass = '\\' . $enumClass;
        }

        return new static($name, $enumClass);
    }

    /**
     * Create a mixed type field.
     */
    public static function mixed(string $name): static
    {
        return new static($name, 'mixed');
    }

    /**
     * Create a union type field.
     *
     * @example Field::union('id', 'int', 'string') // Creates 'int|string'
     * @example Field::union('value', 'int', 'float', 'string') // Creates 'int|float|string'
     *
     * @throws \InvalidArgumentException
     */
    public static function union(string $name, string ...$types): static
    {
        if (count($types) < 2) {
            throw new InvalidArgumentException('Union types require at least 2 types');
        }

        return new static($name, implode('|', $types));
    }

    /**
     * Create a field with explicit type.
     */
    public static function of(string $name, string $type): static
    {
        return new static($name, $type);
    }

    /**
     * Mark field as required.
     */
    public function required(): static
    {
        $this->required = true;

        return $this;
    }

    /**
     * Set default value.
     */
    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefaultValue = true;

        return $this;
    }

    /**
     * Mark as collection (ArrayObject).
     */
    public function asCollection(?string $collectionType = null): static
    {
        $this->collection = true;
        $this->collectionType = $collectionType;

        return $this;
    }

    /**
     * Set singular name for collection add methods.
     */
    public function singular(string $name): static
    {
        $this->singular = $name;

        return $this;
    }

    /**
     * Mark collection as associative.
     */
    public function associative(?string $key = null): static
    {
        $this->associative = true;
        $this->key = $key;

        return $this;
    }

    /**
     * Mark field as deprecated.
     */
    public function deprecated(string $message = ''): static
    {
        $this->deprecated = $message ?: 'true';

        return $this;
    }

    /**
     * Set factory method for instantiation.
     */
    public function factory(string $method): static
    {
        $this->factory = $method;

        return $this;
    }

    /**
     * Set serialization mode.
     */
    public function serialize(string $mode): static
    {
        $this->serialize = $mode;

        return $this;
    }

    /**
     * Map from a different input key name.
     *
     * When hydrating from array, this source key will be mapped to this field.
     *
     * @example Field::string('emailAddress')->mapFrom('email')
     *          // Input: ['email' => 'john@example.com']
     *          // Field: emailAddress = 'john@example.com'
     */
    public function mapFrom(string $sourceKey): static
    {
        $this->mapFrom = $sourceKey;

        return $this;
    }

    /**
     * Map to a different output key name.
     *
     * When converting to array, the field will be output with this key.
     *
     * @example Field::string('emailAddress')->mapTo('email_address')
     *          // Field: emailAddress = 'john@example.com'
     *          // Output: ['email_address' => 'john@example.com']
     */
    public function mapTo(string $outputKey): static
    {
        $this->mapTo = $outputKey;

        return $this;
    }

    /**
     * Transform input value before hydration.
     *
     * @example Field::string('email')->transformFrom('App\\Transform\\Email::normalize')
     */
    public function transformFrom(string $callable): static
    {
        $this->transformFrom = $callable;

        return $this;
    }

    /**
     * Transform output value after serialization.
     *
     * @example Field::string('email')->transformTo('App\\Transform\\Email::mask')
     */
    public function transformTo(string $callable): static
    {
        $this->transformTo = $callable;

        return $this;
    }

    /**
     * Get field name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the field configuration array.
     *
     * @return array<string, mixed>|string
     */
    public function toArray(): array|string
    {
        // Simple case: just a type string
        if (
            !$this->required &&
            !$this->hasDefaultValue &&
            !$this->collection &&
            $this->singular === null &&
            !$this->associative &&
            $this->deprecated === null &&
            $this->factory === null &&
            $this->serialize === null &&
            $this->mapFrom === null &&
            $this->mapTo === null &&
            $this->transformFrom === null &&
            $this->transformTo === null
        ) {
            return $this->type;
        }

        $config = ['type' => $this->type];

        if ($this->required) {
            $config['required'] = true;
        }

        if ($this->hasDefaultValue) {
            $config['defaultValue'] = $this->defaultValue;
        }

        if ($this->collection) {
            $config['collection'] = true;
        }

        if ($this->collectionType !== null) {
            $config['collectionType'] = $this->collectionType;
        }

        if ($this->singular !== null) {
            $config['singular'] = $this->singular;
        }

        if ($this->associative) {
            $config['associative'] = true;
        }

        if ($this->key !== null) {
            $config['key'] = $this->key;
        }

        if ($this->deprecated !== null) {
            $config['deprecated'] = $this->deprecated;
        }

        if ($this->factory !== null) {
            $config['factory'] = $this->factory;
        }

        if ($this->serialize !== null) {
            $config['serialize'] = $this->serialize;
        }

        if ($this->mapFrom !== null) {
            $config['mapFrom'] = $this->mapFrom;
        }

        if ($this->mapTo !== null) {
            $config['mapTo'] = $this->mapTo;
        }

        if ($this->transformFrom !== null) {
            $config['transformFrom'] = $this->transformFrom;
        }

        if ($this->transformTo !== null) {
            $config['transformTo'] = $this->transformTo;
        }

        return $config;
    }
}
