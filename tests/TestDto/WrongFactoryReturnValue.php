<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

class WrongFactoryReturnValue
{
    public static function create(string $value): string
    {
        return $value;
    }
}
