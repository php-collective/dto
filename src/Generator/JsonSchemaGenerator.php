<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Generates JSON Schema from DTO definitions.
 */
class JsonSchemaGenerator
{
    /**
     * @var array<string, string>
     */
    protected const TYPE_MAP = [
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'double' => 'number',
        'string' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'array',
        'mixed' => 'any',
        'object' => 'object',
        'null' => 'null',
    ];

    /**
     * @var array<string>
     */
    protected const DATE_TYPES = [
        '\\DateTime',
        '\\DateTimeImmutable',
        '\\DateTimeInterface',
        'DateTime',
        'DateTimeImmutable',
        'DateTimeInterface',
    ];

    /**
     * @var \PhpCollective\Dto\Generator\IoInterface
     */
    protected IoInterface $io;

    /**
     * @var array<string, mixed>
     */
    protected array $options;

    /**
     * @param \PhpCollective\Dto\Generator\IoInterface $io
     * @param array<string, mixed> $options
     */
    public function __construct(IoInterface $io, array $options = [])
    {
        $this->io = $io;
        $this->options = $options + [
            'singleFile' => true,
            'schemaVersion' => 'https://json-schema.org/draft/2020-12/schema',
            'suffix' => 'Dto',
            'dateFormat' => 'date-time', // date-time, date, or string
            'useRefs' => true, // Use $ref for nested DTOs
        ];
    }

    /**
     * Generate JSON Schema from DTO definitions.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param string $outputPath
     *
     * @return int Number of files generated
     */
    public function generate(array $definitions, string $outputPath): int
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $outputPath = rtrim($outputPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($this->options['singleFile']) {
            return $this->generateSingleFile($definitions, $outputPath);
        }

        return $this->generateMultipleFiles($definitions, $outputPath);
    }

    /**
     * Generate all schemas in a single file with $defs.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param string $outputPath
     *
     * @return int
     */
    protected function generateSingleFile(array $definitions, string $outputPath): int
    {
        $schema = [
            '$schema' => $this->options['schemaVersion'],
            '$id' => 'dto-schemas.json',
            'title' => 'DTO Schemas',
            'description' => 'Auto-generated JSON Schema from php-collective/dto',
            '$defs' => [],
        ];

        foreach ($definitions as $name => $definition) {
            $schemaName = $this->getSchemaName($name);
            $schema['$defs'][$schemaName] = $this->generateSchemaObject($definition, $definitions, true);
        }

        $filePath = $outputPath . 'dto-schemas.json';
        file_put_contents($filePath, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        $this->io->success('Generated: dto-schemas.json');

        return 1;
    }

    /**
     * Generate each schema in its own file.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param string $outputPath
     *
     * @return int
     */
    protected function generateMultipleFiles(array $definitions, string $outputPath): int
    {
        $count = 0;

        foreach ($definitions as $name => $definition) {
            $schemaName = $this->getSchemaName($name);
            $schema = [
                '$schema' => $this->options['schemaVersion'],
                '$id' => $schemaName . '.json',
                'title' => $schemaName,
                'description' => $definition['description'] ?? 'Auto-generated from ' . $name . ' DTO',
            ] + $this->generateSchemaObject($definition, $definitions, false);

            $fileName = $schemaName . '.json';
            $filePath = $outputPath . $fileName;

            file_put_contents($filePath, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            $this->io->success('Generated: ' . $fileName);
            $count++;
        }

        return $count;
    }

    /**
     * Generate a JSON Schema object for a DTO definition.
     *
     * @param array<string, mixed> $definition
     * @param array<string, array<string, mixed>> $allDefinitions
     * @param bool $inDefs Whether we're in $defs (single file mode)
     *
     * @return array<string, mixed>
     */
    protected function generateSchemaObject(array $definition, array $allDefinitions, bool $inDefs): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        $required = [];
        /** @var array<string, array<string, mixed>> $fields */
        $fields = $definition['fields'] ?? [];

        foreach ($fields as $fieldName => $field) {
            $schema['properties'][$fieldName] = $this->mapFieldToSchema($field, $allDefinitions, $inDefs);

            if (!empty($field['required'])) {
                $required[] = $fieldName;
            }
        }

        if ($required) {
            $schema['required'] = $required;
        }

        // Add additionalProperties: false for strict validation
        $schema['additionalProperties'] = false;

        return $schema;
    }

    /**
     * Map a DTO field to a JSON Schema property.
     *
     * @param array<string, mixed> $field
     * @param array<string, array<string, mixed>> $allDefinitions
     * @param bool $inDefs
     *
     * @return array<string, mixed>
     */
    protected function mapFieldToSchema(array $field, array $allDefinitions, bool $inDefs): array
    {
        /** @var string $type */
        $type = $field['type'] ?? 'mixed';
        $nullable = !empty($field['nullable']);
        $isCollection = !empty($field['collection']) || !empty($field['isArray']);

        // Handle array notation (e.g., "string[]", "int[]")
        if (str_ends_with($type, '[]')) {
            $innerType = substr($type, 0, -2);
            $schema = [
                'type' => 'array',
                'items' => $this->mapScalarTypeToSchema($innerType, $allDefinitions, $inDefs),
            ];

            return $nullable ? $this->makeNullable($schema) : $schema;
        }

        // Handle collections
        if ($isCollection) {
            /** @var string|null $singularType */
            $singularType = $field['singularType'] ?? null;
            /** @var string|null $singularClass */
            $singularClass = $field['singularClass'] ?? null;

            $itemSchema = ['type' => 'any'];
            if ($singularClass) {
                $dtoName = $this->extractDtoName($singularClass);
                if ($dtoName && isset($allDefinitions[$dtoName])) {
                    $itemSchema = $this->getRefOrInline($dtoName, $allDefinitions, $inDefs);
                }
            } elseif ($singularType) {
                $itemSchema = $this->mapScalarTypeToSchema($singularType, $allDefinitions, $inDefs);
            }

            $schema = [
                'type' => 'array',
                'items' => $itemSchema,
            ];

            return $nullable ? $this->makeNullable($schema) : $schema;
        }

        // Handle nested DTO
        if (!empty($field['dto'])) {
            /** @var string $dtoName */
            $dtoName = $field['dto'];
            if (isset($allDefinitions[$dtoName])) {
                $schema = $this->getRefOrInline($dtoName, $allDefinitions, $inDefs);

                return $nullable ? $this->makeNullable($schema) : $schema;
            }
        }

        // Handle scalar types
        $schema = $this->mapScalarTypeToSchema($type, $allDefinitions, $inDefs);

        return $nullable ? $this->makeNullable($schema) : $schema;
    }

    /**
     * Map a scalar or class type to JSON Schema.
     *
     * @param string $type
     * @param array<string, array<string, mixed>> $allDefinitions
     * @param bool $inDefs
     *
     * @return array<string, mixed>
     */
    protected function mapScalarTypeToSchema(string $type, array $allDefinitions, bool $inDefs): array
    {
        // Handle union types
        if (str_contains($type, '|')) {
            $types = explode('|', $type);
            $schemas = [];
            foreach ($types as $t) {
                $t = trim($t);
                if ($t === 'null') {
                    continue; // Handled separately
                }
                $schemas[] = $this->mapSingleTypeToSchema($t, $allDefinitions, $inDefs);
            }

            if (in_array('null', $types, true)) {
                if (count($schemas) === 1) {
                    return $this->makeNullable($schemas[0]);
                }

                return [
                    'oneOf' => array_merge($schemas, [['type' => 'null']]),
                ];
            }

            if (count($schemas) === 1) {
                return $schemas[0];
            }

            return ['oneOf' => $schemas];
        }

        return $this->mapSingleTypeToSchema($type, $allDefinitions, $inDefs);
    }

    /**
     * Map a single type to JSON Schema.
     *
     * @param string $type
     * @param array<string, array<string, mixed>> $allDefinitions
     * @param bool $inDefs
     *
     * @return array<string, mixed>
     */
    protected function mapSingleTypeToSchema(string $type, array $allDefinitions, bool $inDefs): array
    {
        // Check scalar types
        $lowerType = strtolower($type);
        if (isset(self::TYPE_MAP[$lowerType])) {
            $jsonType = self::TYPE_MAP[$lowerType];

            if ($jsonType === 'any') {
                return []; // Empty schema allows anything
            }

            return ['type' => $jsonType];
        }

        // Check date types
        if (in_array($type, self::DATE_TYPES, true)) {
            return [
                'type' => 'string',
                'format' => $this->options['dateFormat'],
            ];
        }

        // Handle FQCN (starts with backslash)
        if (str_starts_with($type, '\\')) {
            // Check if it's a date type
            if (in_array($type, self::DATE_TYPES, true)) {
                return [
                    'type' => 'string',
                    'format' => $this->options['dateFormat'],
                ];
            }

            // Check if it's a known DTO
            $dtoName = $this->extractDtoName($type);
            if ($dtoName && isset($allDefinitions[$dtoName])) {
                return $this->getRefOrInline($dtoName, $allDefinitions, $inDefs);
            }

            // Unknown class - treat as object
            return ['type' => 'object'];
        }

        // Check if it's a DTO reference by name
        if (isset($allDefinitions[$type])) {
            return $this->getRefOrInline($type, $allDefinitions, $inDefs);
        }

        // Unknown type - allow anything
        return [];
    }

    /**
     * Get a $ref or inline schema for a DTO.
     *
     * @param string $dtoName
     * @param array<string, array<string, mixed>> $allDefinitions
     * @param bool $inDefs
     *
     * @return array<string, mixed>
     */
    protected function getRefOrInline(string $dtoName, array $allDefinitions, bool $inDefs): array
    {
        if (!$this->options['useRefs']) {
            return $this->generateSchemaObject($allDefinitions[$dtoName], $allDefinitions, $inDefs);
        }

        $schemaName = $this->getSchemaName($dtoName);

        if ($inDefs) {
            return ['$ref' => '#/$defs/' . $schemaName];
        }

        return ['$ref' => $schemaName . '.json'];
    }

    /**
     * Make a schema nullable.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    protected function makeNullable(array $schema): array
    {
        // If schema has a type, add null to it
        if (isset($schema['type']) && is_string($schema['type'])) {
            return [
                'oneOf' => [
                    $schema,
                    ['type' => 'null'],
                ],
            ];
        }

        // If schema is a $ref, wrap in oneOf
        if (isset($schema['$ref'])) {
            return [
                'oneOf' => [
                    $schema,
                    ['type' => 'null'],
                ],
            ];
        }

        // Already a oneOf or complex schema
        if (isset($schema['oneOf']) && is_array($schema['oneOf'])) {
            $schema['oneOf'][] = ['type' => 'null'];

            return $schema;
        }

        // Fallback: wrap in oneOf
        return [
            'oneOf' => [
                $schema,
                ['type' => 'null'],
            ],
        ];
    }

    /**
     * Get the schema name for a DTO.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getSchemaName(string $name): string
    {
        /** @var string $suffix */
        $suffix = $this->options['suffix'];

        // Add suffix if not present
        if (!str_ends_with($name, $suffix)) {
            return $name . $suffix;
        }

        return $name;
    }

    /**
     * Extract DTO name from a fully qualified class name.
     *
     * @param string $fqcn
     *
     * @return string|null
     */
    protected function extractDtoName(string $fqcn): ?string
    {
        $parts = explode('\\', trim($fqcn, '\\'));
        $className = end($parts);

        /** @var string $suffix */
        $suffix = $this->options['suffix'];

        // Remove suffix if present
        if (str_ends_with($className, $suffix)) {
            return substr($className, 0, -strlen($suffix));
        }

        return $className;
    }
}
