<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Dto;

use PhpCollective\Dto\Utility\Json;
use RuntimeException;

abstract class AbstractDto extends Dto
{
    /**
     * @param array<string, mixed> $data
     * @param bool $ignoreMissing
     * @param string|null $type
     *
     * @return static
     */
    public function fromArray(array $data, bool $ignoreMissing = false, ?string $type = null): static
    {
        if ($type === null && static::HAS_FAST_PATH) {
            if (!$ignoreMissing) {
                $this->validateFieldNames($data);
            }
            $this->setFromArrayFast($data);

            return $this;
        }

        return $this->setFromArray($data, $ignoreMissing, $type);
    }

    /**
     * Convenience method to populate this DTO from a JSON string.
     *
     * This is a shorthand for $dto->fromArray(json_decode($json, true)).
     * Note: This modifies the current instance, unlike static::fromUnserialized()
     * which creates a new instance.
     *
     * @param string $serialized JSON encoded string
     * @param bool $ignoreMissing Whether to ignore unknown fields
     *
     * @return $this
     */
    public function unserialize(string $serialized, bool $ignoreMissing = false)
    {
        $jsonUtil = new Json();
        $this->fromArray($jsonUtil->decode($serialized, true) ?: [], $ignoreMissing);

        return $this;
    }

    /**
     * Magic setter to add or edit a property in this entity
     *
     * @param string $property The name of the property to set
     * @param mixed $value The value to set to the property
     *
     * @return void
     */
    public function __set(string $property, $value): void
    {
        $this->set($property, $value);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @param string|null $type
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function set(string $field, $value, ?string $type = null)
    {
        $type = $this->keyType($type);
        if ($type !== static::TYPE_DEFAULT) {
            $field = $this->field($field, $type);
        }

        if (!isset($this->_metadata[$field])) {
            throw new RuntimeException('Field does not exist: ' . $field);
        }

        $method = 'set' . ucfirst($field);
        $this->$method($value);

        return $this;
    }
}
