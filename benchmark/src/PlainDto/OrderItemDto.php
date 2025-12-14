<?php

declare(strict_types=1);

namespace Benchmark\PlainDto;

/**
 * Plain PHP 8.2+ readonly DTO for order items.
 */
final readonly class OrderItemDto
{
    public function __construct(
        public int $productId,
        public string $name,
        public int $quantity,
        public float $price,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['productId'],
            name: $data['name'],
            quantity: $data['quantity'],
            price: (float)$data['price'],
        );
    }

    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'price' => $this->price,
        ];
    }
}
