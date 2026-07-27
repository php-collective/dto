<?php

declare(strict_types=1);

namespace Benchmark\Support;

use StdOut\SimpleDataObjects\Contracts\CastsValue;

final class UcfirstCast implements CastsValue
{
    /**
     * @param array<string, mixed> $state
     */
    public static function __set_state(array $state): self
    {
        return new self();
    }

    public function get(mixed $value): ?string
    {
        return $value === null ? null : ucfirst(trim((string)$value));
    }

    public function set(mixed $value): ?string
    {
        return $this->get($value);
    }
}
