<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator\Fixtures;

use JsonSerializable;

class JsonSerializableClass implements JsonSerializable
{
    public function __construct(public string $value = '')
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['value' => $this->value];
    }
}
