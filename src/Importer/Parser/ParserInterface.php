<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Parser;

/**
 * Interface for schema parsers that convert input data to DTO definitions.
 */
interface ParserInterface
{
    /**
     * Translates input into DTO field definitions.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $options
     *
     * @return $this
     */
    public function parse(array $input, array $options = []): static;

    /**
     * Returns the parsed result.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function result(): array;
}
