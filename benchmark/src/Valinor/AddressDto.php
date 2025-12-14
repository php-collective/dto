<?php

declare(strict_types=1);

namespace Benchmark\Valinor;

final readonly class AddressDto
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public ?string $zipCode = null,
    ) {
    }
}
