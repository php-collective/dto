<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

class TransformHelper
{
    public static function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower(trim($value));
    }

    public static function maskEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $pos = strpos($value, '@');
        if ($pos === false) {
            return '***';
        }

        $name = substr($value, 0, $pos);
        $domain = substr($value, $pos + 1);
        if ($name === '') {
            return '***@' . $domain;
        }

        return $name[0] . '***@' . $domain;
    }
}
