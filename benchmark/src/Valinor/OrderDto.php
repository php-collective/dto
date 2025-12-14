<?php

declare(strict_types=1);

namespace Benchmark\Valinor;

final readonly class OrderDto
{
    public function __construct(
        public int $id,
        public UserDto $customer,
        public AddressDto $shippingAddress,
        public array $items,
        public float $total,
        public string $status,
        public ?string $createdAt = null,
    ) {
    }
}
