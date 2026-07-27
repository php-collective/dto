<?php

declare(strict_types=1);

namespace Benchmark\SimpleDataObjects;

use StdOut\SimpleDataObjects\BaseData;

class MiniData extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $active,
    ) {
    }
}
