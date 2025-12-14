<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator\Fixtures;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
