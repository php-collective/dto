<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator\Fixtures;

use Stringable;

class StringableClass implements Stringable
{
    public function __construct(public string $value = '')
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
