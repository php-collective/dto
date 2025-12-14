<?php

declare(strict_types=1);

namespace Benchmark\LaravelData;

use Spatie\LaravelData\Data;

class OrderItemData extends Data
{
    public function __construct(
        public int $productId,
        public string $name,
        public int $quantity,
        public float $price,
    ) {
    }
}
