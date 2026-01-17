<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Builder;

/**
 * Interface for schema builders that convert parsed definitions to config format.
 */
interface BuilderInterface
{
    /**
     * Build schema output for a single DTO.
     *
     * @param string $name DTO name
     * @param array<string, array<string, mixed>|string> $fields Field definitions (may include _extends string)
     * @param array<string, mixed> $options
     *
     * @return string
     */
    public function build(string $name, array $fields, array $options = []): string;

    /**
     * Build complete schema output for multiple DTOs.
     *
     * @param array<string, array<string, array<string, mixed>|string>> $definitions
     * @param array<string, mixed> $options
     *
     * @return string
     */
    public function buildAll(array $definitions, array $options = []): string;
}
