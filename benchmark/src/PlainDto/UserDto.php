<?php

declare(strict_types=1);

namespace Benchmark\PlainDto;

/**
 * Plain PHP 8.2+ readonly DTO for benchmark comparison.
 */
final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public bool $active = true,
        public array $roles = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'] ?? null,
            active: $data['active'] ?? true,
            roles: $data['roles'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'active' => $this->active,
            'roles' => $this->roles,
        ];
    }
}
