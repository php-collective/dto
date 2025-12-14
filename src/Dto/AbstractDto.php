<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Dto;

use PhpCollective\Dto\Utility\Json;
use RuntimeException;

abstract class AbstractDto extends Dto {

	/**
	 * @param array<string, mixed> $data
	 * @param bool $ignoreMissing
	 * @param string|null $type
	 * @return $this
	 */
	public function fromArray(array $data, bool $ignoreMissing = false, ?string $type = null) {
		return $this->setFromArray($data, $ignoreMissing, $type);
	}

	/**
	 * Constructs the object
	 *
	 * @link https://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized
	 * @param bool $ignoreMissing
	 * @return $this
	 */
	public function unserialize($serialized, $ignoreMissing = false) {
		$jsonUtil = new Json();
		$this->fromArray($jsonUtil->decode($serialized, true) ?: [], $ignoreMissing);

		return $this;
	}

	/**
	 * Magic setter to add or edit a property in this entity
	 *
	 * @param string $property The name of the property to set
	 * @param mixed $value The value to set to the property
	 * @return void
	 */
	public function __set(string $property, $value): void {
		$this->set($property, $value);
	}

	/**
	 * @param string $field
	 * @param mixed $value
	 * @param string|null $type
	 * @throws \RuntimeException
	 * @return $this
	 */
	public function set(string $field, $value, ?string $type = null) {
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
