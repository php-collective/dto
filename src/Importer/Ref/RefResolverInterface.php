<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Ref;

interface RefResolverInterface
{
    /**
     * Resolve a $ref pointer to a schema fragment.
     *
     * @param string $ref
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $ref, array $options = []): ?array;
}
