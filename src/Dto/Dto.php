<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Dto;

use ArrayAccess;
use ArrayObject;
use Countable;
use InvalidArgumentException;
use PhpCollective\Dto\Utility\Json;
use RuntimeException;
use Serializable;
use Throwable;
use Traversable;
use UnitEnum;

abstract class Dto implements Serializable
{
    /**
     * Convert DTO to array. Implemented by generated DTOs with shaped array return types.
     *
     * @param string|null $type
     * @param array<string>|null $fields
     * @param bool $touched
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(?string $type = null, ?array $fields = null, bool $touched = false): array;

    /**
     * Create DTO from array. Implemented by generated DTOs with shaped array param types.
     *
     * @param array<string, mixed> $data
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return static
     */
    abstract public static function createFromArray(array $data, bool $ignoreMissing = false, ?string $type = null): static;

 /**
  * Default key type for conversions. Can be set globally.
  *
  * @var string|null
  */
    protected static ?string $defaultKeyType = null;

    /**
     * Optional factory for creating collections.
     * Signature: function(array $items): Traversable
     *
     * @var callable|null
     */
    protected static $collectionFactory = null;

    /**
     * Set the default key type for all DTOs.
     *
     * @param string|null $type One of TYPE_DEFAULT, TYPE_CAMEL, TYPE_UNDERSCORED, TYPE_DASHED
     *
     * @return void
     */
    public static function setDefaultKeyType(?string $type): void
    {
        static::$defaultKeyType = $type;
    }

    /**
     * Get the default key type.
     *
     * @return string|null
     */
    public static function getDefaultKeyType(): ?string
    {
        return static::$defaultKeyType;
    }

    /**
     * Set a custom collection factory.
     * The factory receives an array and should return a Traversable.
     *
     * Example for CakePHP:
     * Dto::setCollectionFactory(fn($items) => new \Cake\Collection\Collection($items));
     *
     * @param callable|null $factory
     *
     * @return void
     */
    public static function setCollectionFactory(?callable $factory): void
    {
        static::$collectionFactory = $factory;
    }

    /**
     * Internal implementation for createFromArray.
     * Generated DTOs provide typed public createFromArray() that calls this.
     *
     * @param array<string, mixed> $data
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return static
     */
    protected static function _createFromArrayInternal(array $data, bool $ignoreMissing = false, ?string $type = null): static
    {
        return new static($data, $ignoreMissing, $type);
    }

    /**
     * @param string $data
     * @param bool $ignoreMissing
     *
     * @return static
     */
    public static function fromUnserialized(string $data, bool $ignoreMissing = false)
    {
        $jsonUtil = new Json();

        return new static($jsonUtil->decode($data, true), $ignoreMissing);
    }

    /**
     * Constructs the object
     *
     * @link https://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $data
     * @param bool $ignoreMissing
     *
     * @return static
     */
    public function unserialize(string $data, bool $ignoreMissing = false)
    {
        $jsonUtil = new Json();

        $new = clone($this);
        $new->setFromArray($jsonUtil->decode($data, true) ?: [], $ignoreMissing, static::TYPE_DEFAULT)->setDefaults()->validate();

        return $new;
    }

    /**
     * Convenience wrapper for easier chaining.
     *
     * $myDto = (new MyDto($data))->...()
     * can be
     * $myDto = MyDto::create($data)->...()
     *
     * @param array|null $data
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return static
     */
    public static function create(?array $data = null, bool $ignoreMissing = false, ?string $type = null)
    {
        return new static($data, $ignoreMissing, $type);
    }

    /**
     * Default type, defaults to camelBacked
     *
     * E.g. `myFieldName`
     *
     * @var string
     */
    public const TYPE_DEFAULT = 'default';

    /**
     * For camelBacked input/output.
     *
     * E.g. `myFieldName`
     *
     * @var string
     */
    public const TYPE_CAMEL = 'camel';

    /**
     * For DB and form input/output.
     *
     * E.g. `my_field_name`
     *
     * @var string
     */
    public const TYPE_UNDERSCORED = 'underscored';

    /**
     * For query string usage.
     *
     * E.g. `my-field-name`
     *
     * @var string
     */
    public const TYPE_DASHED = 'dashed';

    /**
     * For templating rendering.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $_metadata = [];

    /**
     * For usage of inflections.
     *
     * @var array<string, array<string, string>>
     */
    protected array $_keyMap = [];

    /**
     * Custom field mapping for input/output name translation.
     * - mapFrom: maps input key names to field names (used in fromArray)
     * - mapTo: maps field names to output key names (used in toArray)
     *
     * @var array<string, array<string, string>>
     */
    protected array $_fieldMap = [];

    /**
     * Holds touched fields.
     *
     * @var array<string, bool>
     */
    protected array $_touchedFields = [];

    /**
     * @param array|null $data
     * @param bool $ignoreMissing
     * @param string|null $type
     */
    public function __construct(?array $data = null, bool $ignoreMissing = false, ?string $type = null)
    {
        if ($data) {
            // Use optimized fast path only in lenient mode (ignoreMissing=true)
            // Strict mode needs full type validation via setFromArray
            if ($ignoreMissing && $type === null && method_exists($this, 'setFromArrayFast')) {
                $this->setFromArrayFast($data);
            } else {
                $this->setFromArray($data, $ignoreMissing, $type);
            }
        }

        $this->setDefaults();
        $this->validate();
    }

    /**
     * @param array<string> $path Path as array of strings.
     * @param mixed|null $default The return value when the path does not exist.
     *
     * @return mixed|null The value fetched from the DTO, or null.
     */
    public function read(array $path, $default = null)
    {
        $data = null;
        foreach ($path as $key) {
            if ($data === null && !$this->has($key)) {
                return $default;
            }
            if ($data === null) {
                $data = $this->get($key);

                continue;
            }
            if ($data instanceof self || $data instanceof FromArrayToArrayInterface) {
                $data = $data->toArray();
            }
            if ((is_array($data) || $data instanceof ArrayAccess) && isset($data[$key])) {
                $data = $data[$key];
            } else {
                return $default;
            }
        }

        return $data;
    }

    /**
     * Internal implementation for toArray.
     * Generated DTOs provide typed public toArray() that calls this.
     *
     * @param string|null $type
     * @param array<string>|null $fields
     * @param bool $touched
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function _toArrayInternal(?string $type = null, ?array $fields = null, bool $touched = false): array
    {
        if ($fields === null) {
            $fields = $this->fields();
        }
        $type = $this->keyType($type);

        $values = [];
        foreach ($fields as $field) {
            $value = $this->$field;

            $key = $field;
            // Apply custom mapTo mapping first
            if (isset($this->_fieldMap['mapTo'][$field])) {
                $key = $this->_fieldMap['mapTo'][$field];
            } elseif ($type !== static::TYPE_DEFAULT) {
                $key = $this->key($field, $type);
            }

            if (is_object($value)) {
                if ($value instanceof self) {
                    $values[$key] = $touched ? $value->touchedToArray($type) : $value->toArray($type);
                } elseif ($value instanceof Countable) {
                    $values = $this->transformCollectionToArray($value, $values, $key, $touched ? 'touchedToArray' : 'toArray', $type);
                } elseif ($this->_metadata[$field]['serialize']) {
                    $values[$key] = $this->transformSerialized($value, $this->_metadata[$field]['serialize']);
                } elseif (!empty($this->_metadata[$field]['enum']) && $this->_metadata[$field]['enum'] === 'unit') {
                    if (!$value instanceof UnitEnum) {
                        throw new InvalidArgumentException('Expected UnitEnum instance');
                    }
                    $values[$key] = $this->transformEnum($value);
                } else {
                    $values[$key] = $value;
                }

                continue;
            }

            if ($this->_metadata[$field]['collectionType'] === 'array') {
                if (!$value) {
                    $value = [];
                }
                $value = $this->transformArrayCollectionToArray($value, $touched ? 'touchedToArray' : 'toArray', $type);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param mixed $value
     * @param array $values
     * @param string $arrayKey
     * @param string $childConvertMethodName
     * @param string $type
     *
     * @return array
     */
    protected function transformCollectionToArray($value, array $values, string $arrayKey, string $childConvertMethodName, string $type): array
    {
        if ($value->count() === 0) {
            $values[$arrayKey] = [];

            return $values;
        }

        foreach ($value as $elementKey => $arrayElement) {
            if (is_array($arrayElement) || is_scalar($arrayElement)) {
                $values[$arrayKey][$elementKey] = $arrayElement;

                continue;
            }

            $values[$arrayKey][$elementKey] = $arrayElement->$childConvertMethodName($type);
        }

        return $values;
    }

    /**
     * @param array $array
     * @param string $childConvertMethodName
     * @param string $type
     *
     * @return array
     */
    protected function transformArrayCollectionToArray(array $array, string $childConvertMethodName, string $type): array
    {
        foreach ($array as $elementKey => $arrayElement) {
            if (is_array($arrayElement) || is_scalar($arrayElement)) {
                continue;
            }

            $array[$elementKey] = $arrayElement->$childConvertMethodName($type);
        }

        return $array;
    }

    /**
     * @param string|null $type
     *
     * @return array<string, mixed>
     */
    public function touchedToArray(?string $type = null): array
    {
        return $this->_toArrayInternal($type, $this->touchedFields(), true);
    }

    /**
     * @param array<string, mixed> $data
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    protected function setFromArray(array $data, bool $ignoreMissing, ?string $type = null)
    {
        $immutable = $this instanceof AbstractImmutableDto;
        $type = $this->keyType($type);
        foreach ($data as $field => $value) {
            if (!$this->hasField($field, $ignoreMissing, $type)) {
                continue;
            }

            if ($type !== static::TYPE_DEFAULT) {
                $field = $this->field($field, $type);
            }

            // Apply custom mapFrom mapping
            if (isset($this->_fieldMap['mapFrom'][$field])) {
                $field = $this->_fieldMap['mapFrom'][$field];
            }

            if ($this->_metadata[$field]['dto']) {
                $value = $this->createDto($field, $value, $ignoreMissing, $type);
            } elseif ($this->_metadata[$field]['collectionType'] && $this->_metadata[$field]['collectionType'] !== 'array') {
                $collectionType = $this->_metadata[$field]['collectionType'];
                $elementType = $this->_metadata[$field]['singularType'];
                if (!$elementType) {
                    throw new RuntimeException('Missing singularType for collection ' . $collectionType);
                }
                // Check if using custom collection factory
                if (static::$collectionFactory !== null && $this->isCustomCollectionType($collectionType)) {
                    $value = $this->createCustomCollection($elementType, $value, $ignoreMissing, $type);
                } else {
                    $value = $this->createCollection($collectionType, $elementType, $value, $ignoreMissing, $type);
                }
            } elseif ($this->_metadata[$field]['collectionType']) {
                $elementType = $this->_metadata[$field]['singularType'];
                $key = $this->_metadata[$field]['associative'];
                if ($this->_metadata[$field]['associative'] && $this->_metadata[$field]['key']) {
                    $key = $this->_metadata[$field]['key'];
                }
                $value = $this->createArrayCollection($elementType, $value, $ignoreMissing, $type, $key);
            } elseif ($this->_metadata[$field]['serialize'] === 'FromArrayToArray') {
                $value = $this->createObject($field, $value);
            } elseif ($this->_metadata[$field]['serialize'] === 'array') {
                $value = $this->createObject($field, $value);
            } elseif ($this->_metadata[$field]['factory']) {
                $value = $this->createWithFactory($field, $value);
            } elseif (!empty($this->_metadata[$field]['isClass']) && !empty($this->_metadata[$field]['enum'])) {
                $value = $this->createEnum($field, $value);
            } elseif (!empty($this->_metadata[$field]['isClass']) && !is_object($value)) {
                $value = $this->createWithConstructor($field, $value, $this->_metadata[$field]);
            }

            if (!$immutable) {
                $method = 'set' . ucfirst($field);
                $this->$method($value);
            } else {
                $this->assertType($field, $value);
                // Defensive copy for arrays to prevent external modification of immutable DTOs
                if (is_array($value)) {
                    $value = $this->cloneArray($value);
                }
                $this->$field = $value;
                $this->_touchedFields[$field] = true;
            }
        }

        return $this;
    }

    /**
     * Check if the collection type should use the custom factory.
     *
     * @param string $collectionType
     *
     * @return bool
     */
    protected function isCustomCollectionType(string $collectionType): bool
    {
        // CakePHP Collection or any custom Traversable that's not ArrayObject
        // is_a() with third param true checks if class is same or subclass
        return !is_a($collectionType, ArrayObject::class, true);
    }

    /**
     * Create a collection using the custom collection factory.
     *
     * @param string $elementType
     * @param \ArrayObject|array $arrayObject
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return \Traversable
     */
    protected function createCustomCollection(string $elementType, $arrayObject, bool $ignoreMissing, ?string $type = null): Traversable
    {
        $items = [];
        foreach ($arrayObject as $arrayElement) {
            if (!is_array($arrayElement)) {
                $items[] = new $elementType();

                continue;
            }

            if (array_values($arrayElement) !== $arrayElement) {
                /** @var \PhpCollective\Dto\Dto\Dto $dto */
                $dto = new $elementType($arrayElement, $ignoreMissing, $type);
                $items[] = $dto;

                continue;
            }

            foreach ($arrayElement as $arrayElementItem) {
                /** @var \PhpCollective\Dto\Dto\Dto $dto */
                $dto = new $elementType($arrayElementItem, $ignoreMissing, $type);
                $items[] = $dto;
            }
        }

        return (static::$collectionFactory)($items);
    }

    /**
     * @param string $field
     * @param static|array $value
     * @param bool $ignoreMissing
     * @param string $type
     *
     * @return static
     */
    protected function createDto(string $field, $value, bool $ignoreMissing, string $type)
    {
        $className = $this->_metadata[$field]['type'];

        if (is_array($value)) {
            /** @var static $value */
            $value = new $className($value, $ignoreMissing, $type);
        }

        return $value;
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return object|string
     */
    protected function createObject(string $field, $value)
    {
        /** @var \PhpCollective\Dto\Dto\FromArrayToArrayInterface $className */
        $className = $this->_metadata[$field]['type'];

        if (is_array($value)) {
            $value = $className::createFromArray($value);
        }

        return $value;
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @throws \RuntimeException If factory method does not exist or fails.
     *
     * @return mixed
     */
    protected function createWithFactory(string $field, $value)
    {
        $factory = $this->_metadata[$field]['factory'];
        $class = $this->_metadata[$field]['type'];
        if ($value instanceof $class) {
            return $value;
        }

        if (str_contains($factory, '::')) {
            [$class, $factory] = explode('::', $factory, 2);
        }

        if (!method_exists($class, $factory)) {
            throw new RuntimeException(sprintf(
                "Factory method '%s' does not exist on class '%s' for field '%s' in %s",
                $factory,
                $class,
                $field,
                static::class,
            ));
        }

        try {
            return $class::$factory($value);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf(
                "Factory method '%s::%s' failed for field '%s' in %s: %s",
                $class,
                $factory,
                $field,
                static::class,
                $e->getMessage(),
            ), 0, $e);
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param array $metadata
     *
     * @return object|null
     */
    protected function createWithConstructor(string $field, $value, array $metadata)
    {
        if (!$metadata['required'] && $value === null && !isset($metadata['defaultValue'])) {
            return null;
        }

        $class = $this->_metadata[$field]['type'];

        return new $class($value);
    }

    /**
     * @param string $collectionType
     * @param string $elementType
     * @param \ArrayObject|array $arrayObject
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return \ArrayObject
     */
    protected function createCollection(string $collectionType, string $elementType, $arrayObject, bool $ignoreMissing, ?string $type = null): ArrayObject
    {
        /** @var \ArrayObject $collection */
        $collection = new $collectionType();
        foreach ($arrayObject as $arrayElement) {
            if (!is_array($arrayElement)) {
                $collection->append(new $elementType());

                continue;
            }

            if (array_values($arrayElement) !== $arrayElement) {
                /** @var \PhpCollective\Dto\Dto\Dto $dto */
                $dto = new $elementType($arrayElement, $ignoreMissing, $type);
                $collection->append($dto);

                continue;
            }

            foreach ($arrayElement as $arrayElementItem) {
                /** @var \PhpCollective\Dto\Dto\Dto $dto */
                $dto = new $elementType($arrayElementItem, $ignoreMissing, $type);
                $collection->append($dto);
            }
        }

        return $collection;
    }

    /**
     * @param string $elementType
     * @param \ArrayObject|array $arrayObject
     * @param bool $ignoreMissing
     * @param string|null $type
     * @param string|bool $key
     *
     * @return array
     */
    protected function createArrayCollection(string $elementType, $arrayObject, bool $ignoreMissing, ?string $type = null, $key = false): array
    {
        $collection = [];
        foreach ($arrayObject as $index => $arrayElement) {
            if (!is_array($arrayElement)) {
                $collection = $this->addValueToArrayCollection($collection, new $elementType(), $arrayElement, $index, $key);

                continue;
            }

            if (array_values($arrayElement) !== $arrayElement) {
                /** @var \PhpCollective\Dto\Dto\Dto $dto */
                $dto = new $elementType($arrayElement, $ignoreMissing, $type);
                $collection = $this->addValueToArrayCollection($collection, $dto, $arrayElement, $index, $key);

                continue;
            }

            foreach ($arrayElement as $arrayElementItem) {
                /** @var \PhpCollective\Dto\Dto\Dto $dto */
                $dto = new $elementType($arrayElementItem, $ignoreMissing, $type);
                $collection = $this->addValueToArrayCollection($collection, $dto, $arrayElement, $index, $key);
            }
        }

        return $collection;
    }

    /**
     * @param array<mixed> $collection
     * @param mixed $element
     * @param mixed $arrayElement
     * @param string|int $index
     * @param string|bool $key
     *
     * @return array
     */
    protected function addValueToArrayCollection(array $collection, $element, $arrayElement, $index, $key): array
    {
        if ($key === false) {
            $collection[] = $element;

            return $collection;
        }
        if ($key === true) {
            $collection[(string)$index] = $element;

            return $collection;
        }

        if (is_array($arrayElement) && isset($arrayElement[$key])) {
            $index = $arrayElement[$key];
        }

        $collection[(string)$index] = $element;

        return $collection;
    }

    /**
     * @param string $field
     * @param bool $ignoreMissing
     * @param string $type
     *
     * @throws \InvalidArgumentException
     *
     * @return bool
     */
    protected function hasField(string $field, bool $ignoreMissing, string $type): bool
    {
        if ($type === static::TYPE_DEFAULT && isset($this->_metadata[$field])) {
            return true;
        }
        if ($type !== static::TYPE_DEFAULT && isset($this->_keyMap[$type][$field])) {
            return true;
        }
        // Check custom mapFrom mapping
        if (isset($this->_fieldMap['mapFrom'][$field])) {
            return true;
        }

        if ($ignoreMissing) {
            return false;
        }

        throw new InvalidArgumentException(
            sprintf('Missing field `%s` in `%s` for type `%s`', $field, static::class, $type),
        );
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->serialize();
    }

    /**
     * Returns an array that can be used to describe the internal state of this
     * object.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'data' => $this->toArray(),
            'touched' => $this->touchedFields(),
            'extends' => get_parent_class($this),
            'immutable' => $this instanceof AbstractImmutableDto,
        ];
    }

    /**
     * String representation of object
     *
     * @link https://php.net/manual/en/serializable.serialize.php
     *
     * @return string the string representation of the object or null
     */
    public function serialize(): string
    {
        $jsonUtil = new Json();

        return $jsonUtil->encode($this->touchedToArray());
    }

    /**
     * @return array<string>
     */
    public function fields(): array
    {
        return array_keys($this->_metadata);
    }

    /**
     * @return array<string>
     */
    public function touchedFields(): array
    {
        return array_keys($this->_touchedFields);
    }

    /**
     * Lookup for dashed or underscored inflection of fields.
     *
     * @param string $name
     * @param string $type Either dashed or underscored
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    public function field(string $name, string $type): string
    {
        if (!isset($this->_keyMap[$type][$name])) {
            throw new InvalidArgumentException(sprintf('Invalid field lookup for type `%s`: `%s` does not exist.', $type, $name));
        }

        return $this->_keyMap[$type][$name];
    }

    /**
     * @param string $key
     * @param string $type
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function key(string $key, string $type): string
    {
        $map = array_flip($this->_keyMap[$type]);
        if (!isset($map[$key])) {
            throw new InvalidArgumentException(sprintf('Invalid field lookup for type `%s`: `%s` does not exist.', $type, $key));
        }

        return $map[$key];
    }

    /**
     * @return $this
     */
    protected function setDefaults()
    {
        foreach ($this->_metadata as $name => $field) {
            if ($field['defaultValue'] === null || $this->$name !== null) {
                continue;
            }

            $this->$name = $field['defaultValue'];
        }

        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validate(): void
    {
        $errors = [];
        foreach ($this->_metadata as $name => $field) {
            if ($field['required'] && $this->$name === null) {
                $errors[] = $name;
            }
        }
        if ($errors) {
            throw new InvalidArgumentException('Required fields missing: ' . implode(', ', $errors));
        }
    }

    /**
     * Magic getter to access properties that have been set in this entity
     *
     * @param string $property Name of the property to access
     *
     * @return mixed
     */
    public function &__get(string $property)
    {
        return $this->get($property);
    }

    /**
     * Returns whether this entity contains a property named $property
     * regardless of if it is empty.
     *
     * @param string $property The property to check.
     *
     * @return bool
     */
    public function __isset(string $property): bool
    {
        return $this->has($property);
    }

    /**
     * @param string $field
     * @param string|null $type
     *
     * @throws \RuntimeException
     *
     * @return bool
     */
    public function has(string $field, ?string $type = null): bool
    {
        $type = $this->keyType($type);
        if ($type !== static::TYPE_DEFAULT) {
            $field = $this->field($field, $type);
        }

        if (!isset($this->_metadata[$field])) {
            throw new RuntimeException('Field does not exist: ' . $field);
        }

        $method = 'has' . ucfirst($field);

        return $this->$method();
    }

    /**
     * @param string $field
     * @param string|null $type
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public function &get(string $field, ?string $type = null)
    {
        $type = $this->keyType($type);
        if ($type !== static::TYPE_DEFAULT) {
            $field = $this->field($field, $type);
        }

        if (!isset($this->_metadata[$field])) {
            throw new RuntimeException('Field does not exist: ' . $field);
        }

        $method = 'get' . ucfirst($field);

        $result = $this->$method();

        return $result;
    }

    /**
     * Scalar and array type checks for mass assignment.
     *
     * @param string $field
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function assertType(string $field, $value): void
    {
        // Missing fields will be checked afterwards
        if ($value === null) {
            return;
        }

        $expectedType = $this->_metadata[$field]['type'];
        $actualType = $this->type($value);
        $types = ['bool', 'int', 'string', 'double'];

        if ($expectedType === 'float' && in_array($actualType, ['float', 'int'], true)) {
            return;
        }

        if (in_array($expectedType, $types, true) || in_array($actualType, $types, true)) {
            if ($actualType === $expectedType) {
                return;
            }

            throw new InvalidArgumentException(sprintf('Type of field `%s` is `%s`, expected `%s`.', $field, $actualType, $expectedType));
        }
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected function type($value): string
    {
        $type = gettype($value);
        if ($type === 'boolean') {
            $type = 'bool';
        } elseif ($type === 'double') {
            $type = 'float';
        } elseif ($type === 'integer') {
            $type = 'int';
        }

        return $type;
    }

    /**
     * @param string|null $type
     *
     * @return string
     */
    protected function keyType(?string $type): string
    {
        if ($type === null) {
            $type = static::$defaultKeyType;
        }

        if ($type === null || $type === static::TYPE_CAMEL) {
            return static::TYPE_DEFAULT;
        }

        return $type;
    }

    /**
     * @param object $value
     * @param string $serialize
     *
     * @throws \InvalidArgumentException
     *
     * @return array|string
     */
    protected function transformSerialized(object $value, string $serialize)
    {
        if ($serialize === 'FromArrayToArray') {
            /** @var \PhpCollective\Dto\Dto\FromArrayToArrayInterface $value */
            return $value->toArray();
        }
        if ($serialize === 'array') {
            // This will not be transformable in the other direction without FromArrayToArrayInterface or mapping

            /** @var \PhpCollective\Dto\Dto\FromArrayToArrayInterface $value */
            return $value->toArray();
        }
        if ($serialize === 'string') {
            /** @var \Stringable $value */
            return (string)$value;
        }

        throw new InvalidArgumentException('Cannot determine serialize type from `' . $serialize . '`.');
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return $this->touchedToArray();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \RuntimeException If serialized data contains unknown fields.
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $unknownFields = array_diff(array_keys($data), array_keys($this->_metadata));
        if ($unknownFields) {
            throw new RuntimeException(sprintf(
                "Unknown field(s) '%s' in serialized data for %s",
                implode("', '", $unknownFields),
                static::class,
            ));
        }

        $this->setFromArray($data, true);
    }

    /**
     * @param class-string<\BackedEnum|\UnitEnum> $field
     * @param \BackedEnum|\UnitEnum|string|int|null $value
     *
     * @return \BackedEnum|\UnitEnum|null
     */
    protected function createEnum(string $field, $value)
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return $value;
        }

        /** @var class-string<\BackedEnum|\UnitEnum> $class */
        $class = $this->_metadata[$field]['type'];

        if ($this->_metadata[$field]['enum'] === 'unit') {
            assert(is_string($value));

            return constant($class . "::{$value}");
        }

        /** @var class-string<\BackedEnum> $class */
        return $class::tryFrom($value);
    }

    /**
     * @param \UnitEnum|null $value
     *
     * @return string|null
     */
    protected function transformEnum(?UnitEnum $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->name;
    }

    /**
     * Creates a deep clone of this DTO, including nested DTOs and collections.
     *
     * This ensures that modifications to nested objects in the clone
     * do not affect the original DTO.
     *
     * @return static
     */
    public function clone(): static
    {
        // Use native clone to avoid constructor validation
        $clone = clone $this;
        foreach ($this->_metadata as $field => $metadata) {
            $value = $this->$field;
            if ($value === null) {
                continue;
            }

            if ($value instanceof self) {
                $clone->$field = $value->clone();
            } elseif (is_array($value)) {
                $clone->$field = $this->cloneArray($value);
            } elseif ($value instanceof ArrayObject) {
                $clone->$field = $this->cloneArrayObject($value);
            } elseif ($value instanceof Traversable) {
                $clone->$field = $this->cloneTraversable($value);
            } elseif (is_object($value)) {
                $clone->$field = clone $value;
            } else {
                $clone->$field = $value;
            }
        }
        $clone->_touchedFields = $this->_touchedFields;

        return $clone;
    }

    /**
     * Deep clones an array, including any nested DTOs.
     *
     * @param array<mixed> $items
     *
     * @return array<mixed>
     */
    protected function cloneArray(array $items): array
    {
        return array_map(function ($item) {
            if ($item instanceof self) {
                return $item->clone();
            }
            if (is_array($item)) {
                return $this->cloneArray($item);
            }
            if (is_object($item)) {
                return clone $item;
            }

            return $item;
        }, $items);
    }

    /**
     * Deep clones an ArrayObject, including any nested DTOs.
     *
     * @param \ArrayObject<array-key, mixed> $arrayObject
     *
     * @return \ArrayObject<array-key, mixed>
     */
    protected function cloneArrayObject(ArrayObject $arrayObject): ArrayObject
    {
        $class = $arrayObject::class;
        $clone = new $class();
        foreach ($arrayObject as $key => $item) {
            if ($item instanceof self) {
                $clone[$key] = $item->clone();
            } elseif (is_object($item)) {
                $clone[$key] = clone $item;
            } else {
                $clone[$key] = $item;
            }
        }

        return $clone;
    }

    /**
     * Deep clones a Traversable, including any nested DTOs.
     * Uses the collection factory if available.
     *
     * @param \Traversable<mixed> $traversable
     *
     * @return \Traversable<mixed>
     */
    protected function cloneTraversable(Traversable $traversable): Traversable
    {
        $items = [];
        foreach ($traversable as $key => $item) {
            if ($item instanceof self) {
                $items[$key] = $item->clone();
            } elseif (is_object($item)) {
                $items[$key] = clone $item;
            } else {
                $items[$key] = $item;
            }
        }

        if (static::$collectionFactory !== null) {
            return (static::$collectionFactory)($items);
        }

        return new ArrayObject($items);
    }
}
