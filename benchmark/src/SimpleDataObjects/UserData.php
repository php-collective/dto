<?php

declare(strict_types=1);

namespace Benchmark\SimpleDataObjects;

use StdOut\SimpleDataObjects\BaseData;

class UserData extends BaseData
{
    /**
     * @param array<string>|null $roles
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly bool $active = true,
        public readonly ?array $roles = null,
    ) {
    }
}
