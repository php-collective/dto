<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Dto;

use RuntimeException;

abstract class AbstractImmutableDto extends Dto
{
 /**
  * @param string $field
  * @param mixed $value
  * @param string|null $type
  *
  * @throws \RuntimeException
  *
  * @return static
  */
    public function with(string $field, $value, ?string $type = null)
    {
        $type = $this->keyType($type);
        if ($type !== static::TYPE_DEFAULT) {
            $field = $this->field($field, $type);
        }

        if (!isset($this->_metadata[$field])) {
            throw new RuntimeException('Field does not exist: ' . $field);
        }

        $method = 'with' . ucfirst($field);

        return $this->$method($value);
    }
}
