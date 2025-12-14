<?php

declare(strict_types=1);

namespace Benchmark\LaravelData;

use Spatie\LaravelData\Data;

class AddressData extends Data
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public ?string $zipCode = null,
    ) {
    }
}
