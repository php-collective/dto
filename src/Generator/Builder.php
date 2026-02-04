<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use PhpCollective\Dto\Engine\EngineInterface;
use PhpCollective\Dto\Engine\FileBasedEngineInterface;
use RuntimeException;

/**
 * Builds DTO definitions from configuration files.
 */
class Builder
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        'finder' => Finder::class,
        'scalarAndReturnTypes' => true,
        'typedConstants' => false,
        'defaultCollectionType' => '\ArrayObject',
        'debug' => false,
        'immutable' => false,
        'suffix' => 'Dto',
        'namespace' => 'App',
    ];

    /**
     * @var \PhpCollective\Dto\Engine\EngineInterface
     */
    protected EngineInterface $engine;

    /**
     * @var \PhpCollective\Dto\Generator\TypeValidator
     */
    protected TypeValidator $typeValidator;

    /**
     * @var \PhpCollective\Dto\Generator\DtoValidator
     */
    protected DtoValidator $dtoValidator;

    /**
     * @var \PhpCollective\Dto\Generator\FieldCompletor
     */
    protected FieldCompletor $fieldCompletor;

    /**
     * @var \PhpCollective\Dto\Generator\ExtendsResolver
     */
    protected ExtendsResolver $extendsResolver;

    /**
     * @var \PhpCollective\Dto\Generator\ArrayShapeBuilder
     */
    protected ArrayShapeBuilder $arrayShapeBuilder;

    /**
     * @var \PhpCollective\Dto\Generator\DependencyAnalyzer
     */
    protected DependencyAnalyzer $dependencyAnalyzer;

    /**
     * Needed for Dto to work dynamically.
     *
     * @var array<string>
     */
    protected array $metaDataKeys = [
        'name',
        'type',
        'isClass',
        'enum',
        'serialize',
        'factory',
        'required',
        'defaultValue',
        'dto',
        'collectionType',
        'singularType',
        'singularTypeHint',
        'singularNullable',
        'associative',
        'key',
        'mapFrom',
        'mapTo',
        'transformFrom',
        'transformTo',
    ];

    /**
     * @param \PhpCollective\Dto\Engine\EngineInterface $engine
     * @param \PhpCollective\Dto\Generator\ConfigInterface|null $config
     */
    public function __construct(EngineInterface $engine, ?ConfigInterface $config = null)
    {
        $this->engine = $engine;

        if ($config !== null) {
            $this->config = array_merge($this->config, $config->all());
        }

        $this->typeValidator = new TypeValidator();
        $typeResolver = new TypeResolver(
            $this->typeValidator,
            (bool)$this->config['scalarAndReturnTypes'],
        );
        $this->arrayShapeBuilder = new ArrayShapeBuilder($this->config['suffix']);

        $this->dtoValidator = new DtoValidator($this->typeValidator);
        $this->fieldCompletor = new FieldCompletor(
            $this->typeValidator,
            $typeResolver,
            $this->arrayShapeBuilder,
            $this->config,
        );
        $this->extendsResolver = new ExtendsResolver($this->config['suffix']);
        $this->dependencyAnalyzer = new DependencyAnalyzer($this->config['suffix']);
    }

    /**
     * Get a configuration value.
     *
     * @param string $key
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public function getConfigOrFail(string $key): mixed
    {
        if (!array_key_exists($key, $this->config)) {
            throw new RuntimeException("Configuration key `{$key}` not found");
        }

        return $this->config[$key];
    }

    /**
     * Build DTO definitions from configuration path.
     *
     * @param string $configPath
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function build(string $configPath, array $options = []): array
    {
        $options += [
            'plugin' => null,
            'namespace' => null,
        ];
        $namespace = $this->getNamespace($options['namespace'], $options['plugin']);

        $files = $this->getFiles($configPath);
        $config = $this->parseFiles($files);
        $result = $this->mergeConfigs($config);

        // Detect circular dependencies before processing
        $this->dependencyAnalyzer->analyze($result);

        return $this->createDtos($result, $namespace);
    }

    /**
     * Parse configuration files.
     *
     * @param array<string> $files
     *
     * @return array<string, mixed>
     */
    protected function parseFiles(array $files): array
    {
        $config = [];

        foreach ($files as $file) {
            if ($this->engine instanceof FileBasedEngineInterface) {
                $config[$file] = $this->engine->parseFile($file);
            } else {
                $content = file_get_contents($file) ?: '';
                $config[$file] = $this->engine->parse($content);
            }
        }

        return $config;
    }

    /**
     * Create DTOs from merged configuration.
     *
     * @param array<string, mixed> $config
     * @param string $namespace
     *
     * @return array<string, mixed>
     */
    protected function createDtos(array $config, string $namespace): array
    {
        // Phase 1: Validate and complete each DTO
        foreach ($config as $name => $dto) {
            $this->dtoValidator->validate($dto);
            $dto = $this->fieldCompletor->complete($dto, $namespace);
            $dto = $this->fieldCompletor->completeMeta($dto, $namespace);

            $dto += [
                'immutable' => $this->config['immutable'],
                'namespace' => $namespace . '\Dto',
                'className' => $name . $this->getConfigOrFail('suffix'),
                'extends' => '\\PhpCollective\\Dto\\Dto\\AbstractDto',
                'traits' => [],
            ];

            $dto['traits'] = $this->normalizeTraits($dto['traits']);

            if (!empty($dto['immutable']) && $dto['extends'] === '\\PhpCollective\\Dto\\Dto\\AbstractDto') {
                $dto['extends'] = '\\PhpCollective\\Dto\\Dto\\AbstractImmutableDto';
            }

            $config[$name] = $dto;
        }

        // Phase 2: Resolve extends references
        $config = $this->extendsResolver->resolve($config);

        // Phase 3: Merge base config into DTOs with extends
        foreach ($config as $name => $dto) {
            $config[$name] += $this->config;
        }

        // Phase 4: Resolve namespace paths
        $config = $this->extendsResolver->resolveNamespacePaths($config, $namespace);

        // Phase 5: Add array shapes for PHPDoc
        foreach ($config as $name => $dto) {
            $config[$name]['arrayShape'] = $this->arrayShapeBuilder->buildArrayShape($dto['fields'], $config, $dto);
            $config[$name]['metaData'] = $this->metaData($dto['fields']);
        }

        return $config;
    }

    /**
     * Merge multiple configuration arrays.
     *
     * @param array<string, mixed> $configs
     *
     * @return array<string, mixed>
     */
    protected function mergeConfigs(array $configs): array
    {
        $result = [];

        foreach ($configs as $config) {
            $result += $config;

            foreach ($config as $name => $dto) {
                $this->dtoValidator->validateMerge($result[$name], $dto);
                $result[$name] += $dto;
            }
        }

        return $result;
    }

    /**
     * Get configuration files from path.
     *
     * @param string $configPath
     *
     * @return array<string>
     */
    protected function getFiles(string $configPath): array
    {
        $extension = $this->engine->extension();
        $files = $this->finder()->collect($configPath, $extension);

        $this->engine->validate($files);

        return $files;
    }

    /**
     * Get namespace from options.
     *
     * @param string|null $namespace
     * @param string|null $plugin
     *
     * @return string
     */
    protected function getNamespace(?string $namespace, ?string $plugin): string
    {
        if ($namespace) {
            return $namespace;
        }
        if ($plugin) {
            return str_replace('/', '\\', $plugin);
        }

        return $this->config['namespace'];
    }

    /**
     * Build metadata from fields.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    protected function metaData(array $fields): array
    {
        $meta = [];

        if ($this->config['debug']) {
            return $fields;
        }

        $neededFields = array_combine($this->metaDataKeys, $this->metaDataKeys) ?: [];

        foreach ($fields as $name => $field) {
            $meta[$name] = array_intersect_key($field, $neededFields);
        }

        return $meta;
    }

    /**
     * Get finder instance.
     *
     * @return \PhpCollective\Dto\Generator\FinderInterface
     */
    protected function finder(): FinderInterface
    {
        /** @phpstan-var class-string<\PhpCollective\Dto\Generator\Finder> $finderClass */
        $finderClass = $this->config['finder'];

        return new $finderClass();
    }

    /**
     * Normalize traits configuration to an array of fully qualified class names.
     *
     * @param array<string>|string|null $traits
     *
     * @return array<string>
     */
    protected function normalizeTraits(string|array|null $traits): array
    {
        if ($traits === null || $traits === '' || $traits === []) {
            return [];
        }

        if (is_string($traits)) {
            $traits = array_map('trim', explode(',', $traits));
        }

        return array_map(function (string $trait): string {
            $trait = trim($trait);
            if ($trait !== '' && $trait[0] !== '\\') {
                return '\\' . $trait;
            }

            return $trait;
        }, array_filter($traits));
    }
}
