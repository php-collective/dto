<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator\Fixtures;

class ToArrayClass
{
    public function __construct(public string $value = '')
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['value' => $this->value];
    }
}
