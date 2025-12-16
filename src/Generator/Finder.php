<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use DirectoryIterator;

class Finder implements FinderInterface
{
    /**
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
        }
        if (file_exists($configPath . 'dto.' . $extension)) {
            $files[] = $configPath . 'dto.' . $extension;
        }

        return $files;
    }
}
