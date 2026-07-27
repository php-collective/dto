<?php

declare(strict_types=1);

namespace Benchmark\SimpleDataObjects;

use DateTimeImmutable;
use StdOut\SimpleDataObjects\Attributes\Cast;
use StdOut\SimpleDataObjects\Attributes\DataCollection;
use StdOut\SimpleDataObjects\BaseData;
use StdOut\SimpleDataObjects\Casts\DateTimeImmutableCast;
use StdOut\SimpleDataObjects\TypedDataCollection;

class OrderData extends BaseData
{
    public function __construct(
        public readonly int $id,
        public readonly UserData $customer,
        public readonly AddressData $shippingAddress,
        #[DataCollection(OrderItemData::class)]
        public readonly TypedDataCollection $items,
        public readonly float $total,
        public readonly string $status,
        #[Cast(new DateTimeImmutableCast('Y-m-d H:i:s', 'Y-m-d H:i:s'))]
        public readonly ?DateTimeImmutable $createdAt = null,
    ) {
    }
}
