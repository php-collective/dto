<?php

declare(strict_types=1);

namespace Benchmark\Support;

class Transform
{
    public static function normalizeEmail(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function ucfirstTrim(string $value): string
    {
        return ucfirst(trim($value));
    }

    public static function upperTrim(string $value): string
    {
        return strtoupper(trim($value));
    }
}
