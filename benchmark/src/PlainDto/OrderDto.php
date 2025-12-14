<?php

declare(strict_types=1);

namespace Benchmark\PlainDto;

use DateTimeImmutable;

/**
 * Plain PHP 8.2+ readonly DTO with nested objects for benchmark.
 */
final readonly class OrderDto
{
    /**
     * @param int $id
     * @param \Benchmark\PlainDto\UserDto $customer
     * @param \Benchmark\PlainDto\AddressDto $shippingAddress
     * @param array<int, \Benchmark\PlainDto\OrderItemDto> $items
     * @param float $total
     * @param string $status
     * @param \DateTimeImmutable|null $createdAt
     */
    public function __construct(
        public int $id,
        public UserDto $customer,
        public AddressDto $shippingAddress,
        public array $items,
        public float $total,
        public string $status,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            customer: UserDto::fromArray($data['customer']),
            shippingAddress: AddressDto::fromArray($data['shippingAddress']),
            items: array_map(
                fn (array $item) => OrderItemDto::fromArray($item),
                $data['items'] ?? [],
            ),
            total: (float)$data['total'],
            status: $data['status'],
            createdAt: isset($data['createdAt'])
                ? new DateTimeImmutable($data['createdAt'])
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->customer->toArray(),
            'shippingAddress' => $this->shippingAddress->toArray(),
            'items' => array_map(fn (OrderItemDto $item) => $item->toArray(), $this->items),
            'total' => $this->total,
            'status' => $this->status,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
