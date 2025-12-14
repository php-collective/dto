<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

/**
 * Interface for engines that need direct file access (e.g., PHP config files).
 *
 * Engines implementing this interface will have parseFile() called instead of
 * file_get_contents() + parse().
 */
interface FileBasedEngineInterface extends EngineInterface
{
    /**
     * Parses a file directly into an array form.
     *
     * @param string $filePath
     *
     * @return array<string, mixed>
     */
    public function parseFile(string $filePath): array;
}
