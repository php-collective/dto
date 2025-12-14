<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator\Fixtures;

use PhpCollective\Dto\Dto\FromArrayToArrayInterface;

class FromArrayToArrayClass implements FromArrayToArrayInterface
{
    public function __construct(public string $value = '')
    {
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return static
     */
    public static function createFromArray(array $array): static
    {
        return new static($array['value'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
