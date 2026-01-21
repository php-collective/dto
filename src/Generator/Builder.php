<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use InvalidArgumentException;
use PhpCollective\Dto\Dto\AbstractDto;
use PhpCollective\Dto\Dto\AbstractImmutableDto;
use PhpCollective\Dto\Engine\EngineInterface;
use PhpCollective\Dto\Engine\FileBasedEngineInterface;
use PhpCollective\Dto\Utility\Inflector;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

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
     * @var \PhpCollective\Dto\Generator\TypeResolver
     */
    protected TypeResolver $typeResolver;

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
     * @var array
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
        $this->typeResolver = new TypeResolver(
            $this->typeValidator,
            (bool)$this->config['scalarAndReturnTypes'],
        );
        $this->arrayShapeBuilder = new ArrayShapeBuilder($this->config['suffix']);
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
            throw new RuntimeException("Configuration key '{$key}' not found");
        }

        return $this->config[$key];
    }

    /**
     * @param string $configPath
     * @param array<string, mixed> $options
     *
     * @return array
     */
    public function build(string $configPath, array $options = []): array
    {
        $options += [
            'plugin' => null,
            'namespace' => null,
        ];
        $namespace = $this->_getNamespace($options['namespace'], $options['plugin']);

        $files = $this->_getFiles($configPath);

        $config = [];
        foreach ($files as $file) {
            if ($this->engine instanceof FileBasedEngineInterface) {
                $config[$file] = $this->engine->parseFile($file);
            } else {
                $content = file_get_contents($file) ?: '';
                $config[$file] = $this->engine->parse($content);
            }
        }

        $result = $this->_merge($config);

        // Detect circular dependencies before processing
        $this->dependencyAnalyzer->analyze($result);

        return $this->_createDtos($result, $namespace);
    }

    /**
     * @param array<string, mixed> $config
     * @param string $namespace
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function _createDtos(array $config, string $namespace): array
    {
        foreach ($config as $name => $dto) {
            $this->_validateDto($dto);
            $dto = $this->_complete($dto, $namespace);
            $dto = $this->_completeMeta($dto, $namespace);

            $dto += [
                'immutable' => $this->config['immutable'],
                'namespace' => $namespace . '\Dto',
                'className' => $name . $this->getConfigOrFail('suffix'),
                'extends' => '\\PhpCollective\\Dto\\Dto\\AbstractDto',
                'traits' => [],
            ];

            // Normalize traits to array format
            $dto['traits'] = $this->normalizeTraits($dto['traits']);

            if (!empty($dto['immutable']) && $dto['extends'] === '\\PhpCollective\\Dto\\Dto\\AbstractDto') {
                $dto['extends'] = '\\PhpCollective\\Dto\\Dto\\AbstractImmutableDto';
            }

            $config[$name] = $dto;
        }

        foreach ($config as $name => $dto) {
            if (in_array($dto['extends'], ['\\PhpCollective\\Dto\\Dto\\AbstractDto', '\\PhpCollective\\Dto\\Dto\\AbstractImmutableDto'], true)) {
                continue;
            }

            $extendedDto = $dto['extends'];
            $isImmutable = !empty($dto['immutable']);

            if (isset($config[$extendedDto])) {
                $config[$name]['extends'] = $extendedDto . $this->getConfigOrFail('suffix');
                if (!$isImmutable && !empty($config[$extendedDto]['immutable'])) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid 'extends' attribute for '%s' DTO: cannot extend immutable DTO '%s'.\n"
                        . "Hint: Either make '%s' immutable, or extend a mutable DTO instead.",
                        $dto['name'],
                        $dto['extends'],
                        $dto['name'],
                    ));
                }
                if ($isImmutable && empty($config[$extendedDto]['immutable'])) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid 'extends' attribute for '%s' DTO: immutable DTO cannot extend mutable DTO '%s'.\n"
                        . "Hint: Either make '%s' mutable, or make '%s' immutable.",
                        $dto['name'],
                        $dto['extends'],
                        $dto['name'],
                        $dto['extends'],
                    ));
                }
            } else {
                try {
                    $extendedDtoReflectionClass = new ReflectionClass($extendedDto);
                } catch (ReflectionException $e) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid 'extends' attribute for '%s' DTO: class '%s' does not exist.\n"
                        . 'Hint: Check the class name spelling and ensure the class is autoloadable.',
                        $dto['name'],
                        $dto['extends'],
                    ));
                }

                if ($extendedDtoReflectionClass->getParentClass() === false) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid 'extends' attribute for '%s' DTO: '%s' must extend %s.\n"
                        . 'Hint: The parent class should be a DTO class extending the appropriate base.',
                        $dto['name'],
                        $dto['extends'],
                        $isImmutable ? AbstractImmutableDto::class : AbstractDto::class,
                    ));
                }
                if ($isImmutable && !$extendedDtoReflectionClass->isSubclassOf(AbstractImmutableDto::class)) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid 'extends' attribute for '%s' DTO: '%s' is not immutable.\n"
                        . 'Hint: Immutable DTOs must extend other immutable DTOs or AbstractImmutableDto.',
                        $dto['name'],
                        $dto['extends'],
                    ));
                }
                if (!$isImmutable && !$extendedDtoReflectionClass->isSubclassOf(AbstractDto::class)) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid 'extends' attribute for '%s' DTO: '%s' is immutable.\n"
                        . "Hint: Mutable DTOs cannot extend immutable DTOs. Either make '%s' immutable or change the parent.",
                        $dto['name'],
                        $dto['extends'],
                        $dto['name'],
                    ));
                }
            }

            $config[$name] += $this->config;
        }

        foreach ($config as $name => $dto) {
            if (strpos($dto['className'], '/') !== false) {
                $pieces = explode('/', $dto['className']);
                $dto['className'] = array_pop($pieces);
                $dto['namespace'] .= '\\' . implode('\\', $pieces);
            }
            if (!empty($dto['extends']) && strpos($dto['extends'], '/') !== false) {
                $pieces = explode('/', $dto['extends']);
                $dto['extends'] = '\\' . $namespace . '\Dto\\' . implode('\\', $pieces);
            }

            $config[$name] = $dto;
        }

        // Add shaped array types for toArray()/createFromArray() PHPDoc
        foreach ($config as $name => $dto) {
            $config[$name]['arrayShape'] = $this->arrayShapeBuilder->buildArrayShape($dto['fields'], $config, $dto);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $dto
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function _validateDto(array $dto): void
    {
        if (empty($dto['name'])) {
            throw new InvalidArgumentException(
                "DTO name missing, but required.\n"
                . 'Hint: Each DTO definition must have a "name" attribute.',
            );
        }
        $dtoName = $dto['name'];
        if (!$this->typeValidator->isValidDto($dtoName)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid DTO name '%s'.\n"
                . 'Hint: DTO names must be PascalCase starting with uppercase letter (e.g., "UserProfile", "OrderItem").',
                $dtoName,
            ));
        }

        $fields = $dto['fields'];

        foreach ($fields as $name => $array) {
            if (empty($array['name'])) {
                throw new InvalidArgumentException(sprintf(
                    "Field attribute 'name' missing for field '%s' in '%s' DTO.\n"
                    . 'Hint: Each field must have a "name" attribute.',
                    $name,
                    $dtoName,
                ));
            }
            if (empty($array['type'])) {
                throw new InvalidArgumentException(sprintf(
                    "Field attribute 'type' missing for field '%s' in '%s' DTO.\n"
                    . 'Hint: Each field must have a "type" attribute (e.g., "string", "int", "ItemDto[]").',
                    $name,
                    $dtoName,
                ));
            }
            foreach ($array as $key => $value) {
                $expected = Inflector::variable(Inflector::underscore($key));
                if ($key !== $expected) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid field attribute '%s' for field '%s' in '%s' DTO.\n"
                        . "Hint: Expected '%s' (camelCase format).",
                        $key,
                        $name,
                        $dtoName,
                        $expected,
                    ));
                }
            }

            if (!$this->typeValidator->isValidName($array['name'])) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid field name '%s' in '%s' DTO.\n"
                    . 'Hint: Field names must be alphanumeric starting with a letter (e.g., "userName", "itemCount").',
                    $array['name'],
                    $dtoName,
                ));
            }
            if (!$this->typeValidator->isValidType($array['type'])) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid type '%s' for field '%s' in '%s' DTO.\n"
                    . 'Hint: Valid types include: scalar types (int, string, bool, float), '
                    . 'DTO references (OtherDto), arrays (string[], OtherDto[]), '
                    . 'or fully qualified class names (\\App\\MyClass).',
                    $array['type'],
                    $name,
                    $dtoName,
                ));
            }

            if (!empty($array['collection'])) {
                if (!$this->typeValidator->isValidArray($array['type']) || !$this->typeValidator->isValidCollection($array['type'])) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid collection type '%s' for field '%s' in '%s' DTO.\n"
                        . 'Hint: Collection types must use array notation (e.g., "string[]", "ItemDto[]").',
                        $array['type'],
                        $name,
                        $dtoName,
                    ));
                }
            }

            if (!empty($array['singular'])) {
                $expected = Inflector::variable(Inflector::underscore($array['singular']));
                if ($array['singular'] !== $expected) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid singular name '%s' for field '%s' in '%s' DTO.\n"
                        . "Hint: Expected '%s' (camelCase format).",
                        $array['singular'],
                        $name,
                        $dtoName,
                        $expected,
                    ));
                }

                if (isset($array['collection']) && $array['collection'] === false) {
                    throw new InvalidArgumentException(sprintf(
                        "Invalid 'singular' attribute for non-collection field '%s' in '%s' DTO.\n"
                        . 'Hint: The "singular" attribute is only valid for collection fields.',
                        $name,
                        $dtoName,
                    ));
                }
            }
        }

        // Check for method name collisions between underscore-prefixed and non-prefixed fields
        // e.g., '_foo' and 'foo' would both generate 'getFoo()', 'setFoo()', etc.
        $methodNames = [];
        foreach ($fields as $array) {
            $fieldName = $array['name'];
            $methodName = Inflector::camelize($fieldName);

            if (isset($methodNames[$methodName])) {
                throw new InvalidArgumentException(sprintf(
                    "Field name collision in '%s' DTO: fields '%s' and '%s' would generate identical method names.\n"
                    . "Hint: Both fields would generate methods like 'get%s()', 'set%s()', etc. Use only one of these field names.",
                    $dtoName,
                    $methodNames[$methodName],
                    $fieldName,
                    $methodName,
                    $methodName,
                ));
            }
            $methodNames[$methodName] = $fieldName;
        }
    }

    /**
     * @param array<array<string, mixed>> $configs
     *
     * @return array
     */
    protected function _merge(array $configs): array
    {
        $result = [];
        foreach ($configs as $config) {
            $result += $config;

            foreach ($config as $name => $dto) {
                $this->validateMerge($result[$name], $dto);

                $result[$name] += $dto;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $dto
     * @param string $namespace
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function _complete(array $dto, string $namespace): array
    {
        $dtoName = $dto['name'];
        $fields = $dto['fields'];
        foreach ($fields as $field => $data) {
            $data += [
                'required' => false,
                'defaultValue' => null,
                'nullable' => empty($data['required']),
                'returnTypeHint' => null,
                'nullableTypeHint' => null,
                'isArray' => false,
                'dto' => null,
                'collection' => !empty($data['singular']),
                'collectionType' => null,
                'associative' => false,
                'key' => null,
                'deprecated' => null,
                'serialize' => null,
                'factory' => null,
                'mapFrom' => null,
                'mapTo' => null,
            ];
            if ($data['required']) {
                $data['nullable'] = false;
            }

            $fields[$field] = $data;
        }

        foreach ($fields as $key => $field) {
            if ($this->typeValidator->isValidSimpleType($field['type'], $this->typeValidator->getSimpleTypeAdditionsForDocBlock())) {
                continue;
            }
            if ($this->typeValidator->isValidDto($field['type'])) {
                $fields[$key]['dto'] = $field['type'];

                continue;
            }
            if ($this->isCollection($field)) {
                $fields[$key]['collection'] = true;
                $fields[$key]['collectionType'] = $this->typeResolver->collectionType($field, $this->config['defaultCollectionType']);
                $fields[$key]['nullable'] = false;

                $fields[$key] = $this->_completeCollectionSingular($fields[$key], $dtoName, $namespace, $fields);
                $fields[$key]['singularNullable'] = substr($fields[$key]['type'], 0, 1) === '?';

                if (!empty($fields[$key]['singular'])) {
                    $singular = $fields[$key]['singular'];
                    if (!empty($fields[$singular])) {
                        throw new InvalidArgumentException(sprintf(
                            "Invalid singular name '%s' for collection field '%s' in '%s' DTO.\n"
                            . "Hint: The singular name conflicts with existing field '%s'. Use a different singular name.",
                            $singular,
                            $key,
                            $dtoName,
                            $singular,
                        ));
                    }
                }

                if (preg_match('#^([A-Z][a-zA-Z/]+)\[\]$#', $field['type'], $matches)) {
                    $fields[$key]['type'] = $this->typeResolver->dtoTypeToClass($matches[1], $namespace, $this->getConfigOrFail('suffix')) . '[]';
                }

                if ($fields[$key]['singularNullable']) {
                    $fields[$key]['type'] = '(' . $fields[$key]['singularType'] . '|null)[]';
                }

                continue;
            }
            if ($this->typeValidator->isValidArray($field['type'])) {
                $fields[$key]['isArray'] = true;
                if (preg_match('#^([A-Z][a-zA-Z/]+)\[\]$#', $field['type'], $matches)) {
                    $fields[$key]['type'] = $this->typeResolver->dtoTypeToClass($matches[1], $namespace, $this->getConfigOrFail('suffix')) . '[]';
                }

                continue;
            }

            if ($this->typeValidator->isValidInterfaceOrClass($field['type'])) {
                $fields[$key]['isClass'] = true;

                if (empty($fields[$key]['serialize'])) {
                    $fields[$key]['serialize'] = $this->typeResolver->detectSerialize($fields[$key]);
                }

                $fields[$key]['enum'] = $this->typeResolver->enumType($field['type']);

                continue;
            }

            throw new InvalidArgumentException(sprintf(
                "Invalid type '%s' for field '%s' in '%s' DTO.\n"
                . 'Hint: Valid types include: scalar types (int, string, bool, float), '
                . 'DTO references (OtherDto), arrays (string[], OtherDto[]), '
                . 'or fully qualified class names (\\App\\MyClass).',
                $field['type'],
                $key,
                $dtoName,
            ));
        }

        $dto['fields'] = $fields;

        return $dto;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return bool
     */
    protected function isCollection(array $field): bool
    {
        if (!$field['collection'] && !$field['collectionType'] && !$field['associative']) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @param string $dtoName
     * @param string $namespace
     * @param array<string, mixed> $fields
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    protected function _completeCollectionSingular(array $data, string $dtoName, string $namespace, array $fields): array
    {
        $fieldName = $data['name'];
        if (!$data['collection'] && empty($data['collectionType'])) {
            return $data;
        }

        $data['singularType'] = $this->typeResolver->singularType($data['type']);
        if ($data['singularType'] && $this->typeValidator->isValidDto($data['singularType'])) {
            $data['singularType'] = $this->typeResolver->dtoTypeToClass($data['singularType'], $namespace, $this->getConfigOrFail('suffix'));
            $data['singularClass'] = $data['singularType'];
        }

        if (!empty($data['singular'])) {
            return $data;
        }

        $singular = Inflector::singularize($fieldName);
        if ($singular === $fieldName) {
            throw new InvalidArgumentException(sprintf(
                "Cannot auto-singularize field name '%s' in '%s' DTO.\n"
                . "Hint: The field name '%s' has no singular form. Add an explicit 'singular' attribute (e.g., singular=\"%sItem\").",
                $fieldName,
                $dtoName,
                $fieldName,
                $fieldName,
            ));
        }
        // Collision detection - throw exception instead of silent failure
        if (!empty($fields[$singular])) {
            throw new InvalidArgumentException(sprintf(
                "Auto-generated singular '%s' for collection field '%s' in '%s' DTO collides with existing field.\n"
                . "Hint: Add an explicit 'singular' attribute with a unique name to avoid this collision.",
                $singular,
                $fieldName,
                $dtoName,
            ));
        }

        $data['singular'] = $singular;

        return $data;
    }

    /**
     * @param array<string, mixed> $dto
     * @param string $namespace
     *
     * @return array
     */
    protected function _completeMeta(array $dto, string $namespace): array
    {
        $fields = $dto['fields'];

        foreach ($fields as $key => $field) {
            if ($field['dto']) {
                $className = $this->typeResolver->dtoTypeToClass($field['type'], $namespace, $this->getConfigOrFail('suffix'));
                $fields[$key]['type'] = $className;
                $fields[$key]['typeHint'] = $className;
            } else {
                $fields[$key]['typeHint'] = $field['type'];
            }
            $fields[$key]['typeHint'] = $this->typeResolver->typehint($fields[$key]['typeHint']);

            if ($field['collection']) {
                if ($field['collectionType'] === 'array') {
                    $fields[$key]['typeHint'] = 'array';
                    // Generic PHPDoc type for arrays: array<int, ElementType>
                    $fields[$key]['docBlockType'] = $this->arrayShapeBuilder->buildGenericArrayType($field);
                } else {
                    $fields[$key]['typeHint'] = $field['collectionType'];

                    $fields[$key]['type'] .= '|' . $fields[$key]['typeHint'];
                    // Generic PHPDoc type for collections: \ArrayObject<int, ElementType>
                    $fields[$key]['docBlockType'] = $this->arrayShapeBuilder->buildGenericCollectionType($field);
                }
            }
            if ($field['isArray']) {
                if ($field['type'] !== 'array') {
                    $fields[$key]['typeHint'] = 'array';
                    // Generic PHPDoc type for typed arrays: array<int, ElementType>
                    $fields[$key]['docBlockType'] = $this->arrayShapeBuilder->buildGenericArrayType($field);
                }
            }

            if ($fields[$key]['typeHint'] && $this->config['scalarAndReturnTypes']) {
                $fields[$key]['returnTypeHint'] = $fields[$key]['typeHint'];

                // Pre-compute nullable return type hint (for getters)
                if ($fields[$key]['nullable']) {
                    // For union types, use |null suffix instead of ? prefix
                    if ($this->typeResolver->isUnionType($fields[$key]['typeHint'])) {
                        $fields[$key]['nullableReturnTypeHint'] = $fields[$key]['typeHint'] . '|null';
                    } else {
                        $fields[$key]['nullableReturnTypeHint'] = '?' . $fields[$key]['typeHint'];
                    }
                }
            }

            if ($fields[$key]['typeHint'] && $this->config['scalarAndReturnTypes'] && $fields[$key]['nullable']) {
                // For union types, use |null suffix instead of ? prefix
                if ($this->typeResolver->isUnionType($fields[$key]['typeHint'])) {
                    $fields[$key]['nullableTypeHint'] = $fields[$key]['typeHint'] . '|null';
                } else {
                    $fields[$key]['nullableTypeHint'] = '?' . $fields[$key]['typeHint'];
                }
            }

            if ($fields[$key]['collection']) {
                $fields[$key] += [
                    'singularTypeHint' => null,
                    'singularNullable' => false,
                    'singularReturnTypeHint' => null,
                    'singularNullableReturnTypeHint' => null,
                ];
                if ($fields[$key]['singularType']) {
                    $fields[$key]['singularTypeHint'] = $this->typeResolver->typehint($fields[$key]['singularType']);
                }

                if ($fields[$key]['singularTypeHint'] && $this->config['scalarAndReturnTypes']) {
                    $fields[$key]['singularReturnTypeHint'] = $fields[$key]['singularTypeHint'];

                    // Pre-compute nullable singular return type hint for associative collections
                    if ($fields[$key]['singularNullable']) {
                        if ($this->typeResolver->isUnionType($fields[$key]['singularTypeHint'])) {
                            $fields[$key]['singularNullableReturnTypeHint'] = $fields[$key]['singularTypeHint'] . '|null';
                        } else {
                            $fields[$key]['singularNullableReturnTypeHint'] = '?' . $fields[$key]['singularTypeHint'];
                        }
                    }
                }

                // Add key type for associative collections (string) vs indexed (int)
                $fields[$key]['keyType'] = !empty($fields[$key]['associative']) ? 'string' : 'int';
            }
        }

        $dto['fields'] = $fields;
        $dto['metaData'] = $this->metaData($fields);

        $dto += [
            'deprecated' => null,
        ];

        return $dto;
    }

    /**
     * @param array|null $existing
     * @param array|null $new
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function validateMerge(?array $existing, ?array $new): void
    {
        if (!$existing || !$new) {
            return;
        }

        $dtoName = $existing['name'] ?? 'unknown';

        foreach ($existing as $field => $info) {
            if (!isset($new[$field])) {
                continue;
            }
            if (!isset($info['type'])) {
                continue;
            }
            if (!isset($new[$field]['type'])) {
                continue;
            }

            if ($info['type'] !== $new[$field]['type']) {
                throw new RuntimeException(sprintf(
                    "Type mismatch for field '%s' in '%s' DTO during merge.\n"
                    . "Existing type: '%s', new type: '%s'.\n"
                    . 'Hint: Field types must be consistent across all configuration files.',
                    $field,
                    $dtoName,
                    $info['type'],
                    $new[$field]['type'],
                ));
            }
        }
    }

    /**
     * @param string $configPath
     *
     * @return array<string>
     */
    protected function _getFiles(string $configPath): array
    {
        $extension = $this->engine->extension();

        $files = $this->_finder()->collect($configPath, $extension);

        $this->engine->validate($files);

        return $files;
    }

    /**
     * @param string|null $namespace
     * @param string|null $plugin
     *
     * @return string
     */
    protected function _getNamespace(?string $namespace, ?string $plugin): string
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
     * @return \PhpCollective\Dto\Generator\FinderInterface
     */
    protected function _finder(): FinderInterface
    {
        /** @phpstan-var class-string<\PhpCollective\Dto\Generator\Finder> $finderClass */
        $finderClass = $this->config['finder'];

        return new $finderClass();
    }

    /**
     * Normalize traits configuration to an array of fully qualified class names.
     *
     * Supports:
     * - Single string (comma-separated or single trait)
     * - Array of trait names
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
            // Support comma-separated traits in XML
            $traits = array_map('trim', explode(',', $traits));
        }

        // Ensure all traits start with backslash
        return array_map(function (string $trait): string {
            $trait = trim($trait);
            if ($trait !== '' && $trait[0] !== '\\') {
                return '\\' . $trait;
            }

            return $trait;
        }, array_filter($traits));
    }
}
