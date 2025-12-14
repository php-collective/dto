<?php

declare(strict_types=1);

namespace Benchmark\Valinor;

final readonly class OrderItemDto
{
    public function __construct(
        public int $productId,
        public string $name,
        public int $quantity,
        public float $price,
    ) {
    }
}
