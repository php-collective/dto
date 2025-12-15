<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Dto;

/**
 * Implement this interface for your VOs that should be fromArray()/toArray() safe.
 */
interface FromArrayToArrayInterface
{
 /**
  * @param array<string, mixed> $array
  *
  * @return static
  */
    public static function createFromArray(array $array);

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
