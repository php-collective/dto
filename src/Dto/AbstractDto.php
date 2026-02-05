<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Dto;

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
        return $this->setFromArray($data, $ignoreMissing, $type);
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
