<?php

declare(strict_types=1);

namespace Benchmark\Support;

use StdOut\SimpleDataObjects\Contracts\ValuePipe;

final class UcfirstTrimPipe implements ValuePipe
{
    public function handle(mixed $value, string $paramName, callable $next): mixed
    {
        return $next(is_string($value) ? ucfirst(trim($value)) : $value);
    }
}
