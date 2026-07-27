<?php

declare(strict_types=1);

namespace Benchmark\SimpleDataObjects;

use StdOut\SimpleDataObjects\BaseData;

class OrderItemData extends BaseData
{
    public function __construct(
        public readonly int $productId,
        public readonly string $name,
        public readonly int $quantity,
        public readonly float $price,
    ) {
    }
}
