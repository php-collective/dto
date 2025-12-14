<?php

declare(strict_types=1);

namespace Benchmark\LaravelData;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public bool $active = true,
        /** @var array<string> */
        public array $roles = [],
    ) {
    }
}
