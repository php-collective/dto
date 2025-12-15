<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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

        $dtos = $this->generateDtos($definitions);
        $foundDtos = [];
        if (!$options['force']) {
            $foundDtos = $this->findExistingDtos($srcPath . 'Dto' . DIRECTORY_SEPARATOR);
        }

        $returnCode = static::CODE_SUCCESS;
        $changes = 0;
        foreach ($dtos as $name => $content) {
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
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            if ($isModified) {
                $this->io->out('Changes in ' . $name . ' DTO:', 1, IoInterface::VERBOSE);
                $oldContent = file_get_contents($foundDtos[$name]) ?: '';
                $this->_displayDiff($oldContent, $content);
            }
            if (!$options['dryRun']) {
                file_put_contents($target, $content);
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
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }

            if ($isModified) {
                $this->io->out('Changes in ' . $name . ' Mapper:', 1, IoInterface::VERBOSE);
                $oldContent = file_get_contents($foundMappers[$name]) ?: '';
                $this->_displayDiff($oldContent, $content);
            }
            if (!$options['dryRun']) {
                file_put_contents($target, $content);
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
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

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
        return file_get_contents($file) !== $newContent;
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
}
