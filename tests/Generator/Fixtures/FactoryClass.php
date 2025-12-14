<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator\Fixtures;

/**
 * A class with factory methods for testing factory-based creation.
 */
class FactoryClass
{
    public function __construct(public string $value = '')
    {
    }

    /**
     * Factory method that creates from a string.
     *
     * @param string $value
     *
     * @return static
     */
    public static function create(string $value): static
    {
        return new static($value);
    }

    /**
     * Factory method that creates from an array.
     *
     * @param array<string, mixed> $data
     *
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static($data['value'] ?? '');
    }
}
