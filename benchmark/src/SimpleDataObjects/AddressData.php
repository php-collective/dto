<?php

declare(strict_types=1);

namespace Benchmark\SimpleDataObjects;

use StdOut\SimpleDataObjects\BaseData;

class AddressData extends BaseData
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country,
        public readonly ?string $zipCode = null,
    ) {
    }
}
