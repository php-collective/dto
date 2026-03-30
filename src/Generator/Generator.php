<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use Exception;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class Generator
{
    use DiffHelperTrait;

    /**
     * @var int
     */
    public const CODE_CHANGES = 2;

    /**
     * @var int
     */
    public const CODE_SUCCESS = 0;

    /**
     * @var int
     */
    public const CODE_ERROR = 1;

    /**
     * @var \PhpCollective\Dto\Generator\Builder
     */
    protected Builder $builder;

    /**
     * @var \PhpCollective\Dto\Generator\RendererInterface
     */
    protected RendererInterface $renderer;

    /**
     * @var \PhpCollective\Dto\Generator\ConfigInterface
     */
    protected ConfigInterface $config;

    /**
     * @param \PhpCollective\Dto\Generator\Builder $builder
     * @param \PhpCollective\Dto\Generator\RendererInterface $renderer
     * @param \PhpCollective\Dto\Generator\IoInterface $io
     * @param \PhpCollective\Dto\Generator\ConfigInterface|null $config
     */
    public function __construct(Builder $builder, RendererInterface $renderer, IoInterface $io, ?ConfigInterface $config = null)
    {
        $this->builder = $builder;
        $this->renderer = $renderer;
        $this->io = $io;
        $this->config = $config ?? new ArrayConfig([]);
    }

    /**
     * @param string $configPath
     * @param string $srcPath
     * @param array<string, mixed> $options
     *
     * @return int Code
     */
    public function generate(string $configPath, string $srcPath, array $options = []): int
    {
        $options += [
            'force' => false,
            'dryRun' => false,
            'confirm' => false,
            'verbose' => false,
            'mapper' => false,
        ];

        $definitions = [];
        try {
            $definitions = $this->builder->build($configPath, $options);
        } catch (Exception $e) {
            $this->io->abort($e->getMessage());
        }

        // Output any warnings from field validation
        foreach ($this->builder->getWarnings() as $warning) {
            /** @phpstan-ignore function.alreadyNarrowedType (runtime check for custom IoInterface implementations) */
            if (method_exists($this->io, 'warning')) {
                $this->io->warning($warning);
            }
        }
        $this->builder->clearWarnings();

        $dtos = $this->generateDtos($definitions);
        $foundDtos = [];
        if (!$options['force']) {
            $foundDtos = $this->findExistingDtos($srcPath . 'Dto' . DIRECTORY_SEPARATOR);
        }

        $returnCode = static::CODE_SUCCESS;
        $changes = 0;
        $baseDtoPath = realpath($srcPath) . DIRECTORY_SEPARATOR . 'Dto';
        foreach ($dtos as $name => $content) {
            // Validate DTO name doesn't contain path traversal sequences
            if (str_contains($name, '..') || str_contains($name, "\0")) {
                throw new InvalidArgumentException("Invalid DTO name '{$name}': path traversal not allowed");
            }
            $isNew = !isset($foundDtos[$name]);
            $isModified = !$isNew && $this->isModified($foundDtos[$name], $content);

            if (!$isNew && !$isModified) {
                unset($foundDtos[$name]);
                $this->io->out('Skipping: ' . $name . ' DTO', 1, IoInterface::VERBOSE);

                continue;
            }

            $suffix = $this->config->get('suffix', 'Dto');
            $target = $srcPath . 'Dto' . DIRECTORY_SEPARATOR . $name . $suffix . '.php';
            $targetPath = dirname($target);

            // Validate target path is within the expected base directory
            $this->ensureDirectoryExists($targetPath);
            $realTargetPath = realpath($targetPath);
            if ($realTargetPath === false || !str_starts_with($realTargetPath, $baseDtoPath)) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid target path '%s': must be within '%s'",
                    $targetPath,
                    $baseDtoPath,
                ));
            }

            if ($isModified) {
                $this->io->out('Changes in ' . $name . ' DTO:', 1, IoInterface::VERBOSE);
                $oldContent = file_get_contents($foundDtos[$name]) ?: '';
                $this->displayDiff($oldContent, $content);
            }
            if (!$options['dryRun']) {
                $this->writeFile($target, $content);
                if ($options['confirm'] && !$this->checkPhpFileSyntax($target)) {
                    $returnCode = static::CODE_ERROR;
                }
            }
            $changes++;

            unset($foundDtos[$name]);
            $this->io->success(($isModified ? 'Modifying' : 'Creating') . ': ' . $name . ' DTO');
        }

        foreach ($foundDtos as $name => $file) {
            if (!$options['dryRun']) {
                unlink($file);
            }
            $this->io->success('Deleting: ' . $name . ' DTO');
        }

        // Generate mappers if enabled
        if ($options['mapper']) {
            $changes += $this->generateAndWriteMappers($definitions, $srcPath, $options);
        }

        $this->io->verbose('Done, ' . $changes . ' file(s) changed.');

        if ($options['dryRun'] || $options['verbose']) {
            return $changes ? static::CODE_CHANGES : static::CODE_SUCCESS;
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Generate and write mapper files for Doctrine SELECT NEW compatibility.
     *
     * @param array<string, mixed> $definitions
     * @param string $srcPath
     * @param array<string, mixed> $options
     *
     * @return int Number of changes
     */
    protected function generateAndWriteMappers(array $definitions, string $srcPath, array $options): int
    {
        $mappers = $this->generateMappers($definitions);
        $foundMappers = [];
        if (!$options['force']) {
            $foundMappers = $this->findExistingMappers($srcPath . 'Dto' . DIRECTORY_SEPARATOR . 'Mapper' . DIRECTORY_SEPARATOR);
        }

        $changes = 0;
        foreach ($mappers as $name => $content) {
            $isNew = !isset($foundMappers[$name]);
            $isModified = !$isNew && $this->isModified($foundMappers[$name], $content);

            if (!$isNew && !$isModified) {
                unset($foundMappers[$name]);
                $this->io->out('Skipping: ' . $name . ' Mapper', 1, IoInterface::VERBOSE);

                continue;
            }

            $suffix = $this->config->get('suffix', 'Dto');
            $target = $srcPath . 'Dto' . DIRECTORY_SEPARATOR . 'Mapper' . DIRECTORY_SEPARATOR . $name . $suffix . 'Mapper.php';
            $targetPath = dirname($target);
            $this->ensureDirectoryExists($targetPath);

            if ($isModified) {
                $this->io->out('Changes in ' . $name . ' Mapper:', 1, IoInterface::VERBOSE);
                $oldContent = file_get_contents($foundMappers[$name]) ?: '';
                $this->displayDiff($oldContent, $content);
            }
            if (!$options['dryRun']) {
                $this->writeFile($target, $content);
                if ($options['confirm'] && !$this->checkPhpFileSyntax($target)) {
                    // Don't fail the whole process for mapper syntax errors
                    $this->io->error('Mapper syntax error in: ' . $name);
                }
            }
            $changes++;

            unset($foundMappers[$name]);
            $this->io->success(($isModified ? 'Modifying' : 'Creating') . ': ' . $name . ' Mapper');
        }

        foreach ($foundMappers as $name => $file) {
            if (!$options['dryRun']) {
                unlink($file);
            }
            $this->io->success('Deleting: ' . $name . ' Mapper');
        }

        return $changes;
    }

    /**
     * @param string $path
     *
     * @return array<string>
     */
    protected function findExistingDtos(string $path): array
    {
        $this->ensureDirectoryExists($path);

        $files = [];

        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $fileInfo) {
            $file = $fileInfo->getPathname();
            $suffix = $this->config->get('suffix', 'Dto');
            if (!preg_match('#src/Dto/(.+)' . preg_quote($suffix, '#') . '\.php$#', $file, $matches)) {
                continue;
            }
            // Skip mapper files
            if (str_contains($file, DIRECTORY_SEPARATOR . 'Mapper' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $name = $matches[1];
            $files[$name] = $file;
        }

        return $files;
    }

    /**
     * @param string $path
     *
     * @return array<string>
     */
    protected function findExistingMappers(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = [];

        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $fileInfo) {
            $file = $fileInfo->getPathname();
            $suffix = $this->config->get('suffix', 'Dto');
            if (!preg_match('#Mapper/(.+)' . preg_quote($suffix, '#') . 'Mapper\.php$#', $file, $matches)) {
                continue;
            }
            $name = $matches[1];
            $files[$name] = $file;
        }

        return $files;
    }

    /**
     * @param string $file
     * @param string $newContent
     *
     * @return bool
     */
    protected function isModified(string $file, string $newContent): bool
    {
        $oldContent = file_get_contents($file);
        if ($oldContent === false) {
            // If we can't read the file, assume it's modified to be safe
            return true;
        }

        return $oldContent !== $newContent;
    }

    /**
     * @param array<string, mixed> $definitions
     *
     * @return array<string>
     */
    protected function generateDtos(array $definitions): array
    {
        $dtos = [];
        foreach ($definitions as $name => $dto) {
            $this->renderer->set($dto);

            $content = $this->renderer->generate('dto');
            $dtos[$name] = $content;
        }

        return $dtos;
    }

    /**
     * Generate mapper classes for Doctrine SELECT NEW compatibility.
     *
     * @param array<string, mixed> $definitions
     *
     * @return array<string>
     */
    protected function generateMappers(array $definitions): array
    {
        $mappers = [];
        foreach ($definitions as $name => $dto) {
            $this->renderer->set($dto);

            $content = $this->renderer->generate('mapper');
            $mappers[$name] = $content;
        }

        return $mappers;
    }

    /**
     * @param string $file
     *
     * @return bool
     */
    protected function checkPhpFileSyntax(string $file): bool
    {
        exec('php -l ' . escapeshellarg($file), $output, $returnValue);

        if ($returnValue !== static::CODE_SUCCESS) {
            $this->io->error('PHP file invalid: ' . implode("\n", $output));

            return false;
        }

        return true;
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $path
     *
     * @throws \RuntimeException If directory cannot be created.
     *
     * @return void
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        // Use @ to suppress warning, then check result
        if (!@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf(
                "Failed to create directory '%s': %s",
                $path,
                error_get_last()['message'] ?? 'unknown error',
            ));
        }
    }

    /**
     * Write content to a file with proper error handling.
     *
     * @param string $path
     * @param string $content
     *
     * @throws \RuntimeException If file cannot be written.
     *
     * @return void
     */
    protected function writeFile(string $path, string $content): void
    {
        $result = @file_put_contents($path, $content);
        if ($result === false) {
            throw new RuntimeException(sprintf(
                "Failed to write file '%s': %s",
                $path,
                error_get_last()['message'] ?? 'unknown error',
            ));
        }
    }
}
