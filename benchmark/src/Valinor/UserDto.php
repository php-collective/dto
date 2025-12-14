<?php

declare(strict_types=1);

namespace Benchmark\Valinor;

final readonly class UserDto
{
    /**
     * @param int $id
     * @param string $name
     * @param string $email
     * @param string|null $phone
     * @param bool $active
     * @param array<string> $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public bool $active = true,
        public array $roles = [],
    ) {
    }
}
