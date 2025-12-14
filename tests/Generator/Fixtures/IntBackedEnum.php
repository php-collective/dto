<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator\Fixtures;

enum IntBackedEnum: int
{
    case Low = 1;
    case Medium = 5;
    case High = 10;
}
