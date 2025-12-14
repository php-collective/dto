<?php

declare(strict_types=1);

namespace Benchmark\LaravelData;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class OrderData extends Data
{
    public function __construct(
        public int $id,
        public UserData $customer,
        public AddressData $shippingAddress,
        #[DataCollectionOf(OrderItemData::class)]
        public array $items,
        public float $total,
        public string $status,
        public ?string $createdAt = null,
    ) {
    }
}
