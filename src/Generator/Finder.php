<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use DirectoryIterator;

class Finder implements FinderInterface
{
    /**
     * Collects configuration files from the given path.
     *
     * Files are sorted alphabetically to ensure consistent ordering when
     * the same DTO is defined in multiple files. This allows using numbered
     * prefixes (01-base.php, 02-override.php) to control merge order.
     *
     * @param string $configPath
     * @param string $extension
     *
     * @return array<string>
     */
    public function collect(string $configPath, string $extension): array
    {
        $files = [];
        if (is_dir($configPath . 'dto')) {
            $iterator = new DirectoryIterator($configPath . 'dto');
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot() || $fileInfo->getExtension() !== $extension) {
                    continue;
                }
                $files[] = $fileInfo->getPathname();
            }
            // Sort files alphabetically for consistent merge order
            sort($files, SORT_STRING);
        }
        if (file_exists($configPath . 'dto.' . $extension)) {
            $files[] = $configPath . 'dto.' . $extension;
        }

        return $files;
    }
}
