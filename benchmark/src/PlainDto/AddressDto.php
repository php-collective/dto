<?php

declare(strict_types=1);

namespace Benchmark\PlainDto;

/**
 * Plain PHP 8.2+ readonly DTO for nested object benchmark.
 */
final readonly class AddressDto
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public ?string $zipCode = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'],
            city: $data['city'],
            country: $data['country'],
            zipCode: $data['zipCode'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'country' => $this->country,
            'zipCode' => $this->zipCode,
        ];
    }
}
