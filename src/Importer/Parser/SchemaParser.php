<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Parser;

use PhpCollective\Dto\Importer\Ref\FileRefResolver;
use PhpCollective\Dto\Importer\Ref\RefResolverInterface;
use PhpCollective\Dto\Utility\Inflector;
use RuntimeException;

/**
 * Parser that converts JSON Schema definitions to DTO schema.
 */
class SchemaParser implements ParserInterface
{
    /**
     * @var string
     */
    public const NAME = 'Schema';

    /**
     * @var array<string, array<string, array<string, mixed>|string>>
     */
    protected array $result = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $map = [];

    /**
     * Definitions extracted from $defs, definitions, or components/schemas.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $definitions = [];

    /**
     * Track which $ref definitions have been processed to create DTOs.
     *
     * @var array<string, string>
     */
    protected array $processedRefs = [];

    /**
     * @var \PhpCollective\Dto\Importer\Ref\RefResolverInterface|null
     */
    protected ?RefResolverInterface $refResolver = null;

    /**
     * Maximum recursion depth to prevent stack overflow on deeply nested schemas.
     *
     * @var int
     */
    protected const MAX_DEPTH = 50;

    /**
     * @inheritDoc
     *
     * @throws \RuntimeException
     */
    public function parse(array $input, array $options = [], array $parentData = []): static
    {
        // Check recursion depth limit
        $depth = $parentData['_depth'] ?? 0;
        if ($depth > static::MAX_DEPTH) {
            throw new RuntimeException("Maximum schema nesting depth exceeded ({$depth}). Possible circular reference.");
        }
        $parentData['_depth'] = $depth + 1;

        // Extract definitions on first call (no parent data)
        if ($depth === 0) {
            $this->extractDefinitions($input);
            $this->refResolver = $options['refResolver'] ?? new FileRefResolver($options['basePath'] ?? null);

            // Handle OpenAPI documents: parse all schemas from components/schemas
            if ($this->isOpenApi($input)) {
                $this->parseOpenApiSchemas($input, $options);

                return $this;
            }
        }

        // Handle allOf composition (inheritance)
        $extends = null;
        if (!empty($input['allOf'])) {
            [$input, $extends] = $this->processAllOf($input, $options);
        }

        // Skip if no properties and no inheritance
        // But allow DTOs that only extend (inherit all fields from parent)
        if (!$input || (empty($input['properties']) && !$extends)) {
            return $this;
        }

        $dtoName = !empty($input['title']) ? Inflector::camelize($input['title']) : null;
        if (!$dtoName) {
            if (empty($parentData['field'])) {
                $parentData['field'] = '';
            }
            $field = $parentData['field'];
            if (!empty($parentData['collection'])) {
                $field = Inflector::singularize($field);
            }
            $dtoName = $field ? ucfirst($field) : 'Object';
        }

        if (!empty($options['namespace'])) {
            $dtoName = rtrim($options['namespace'], '/') . '/' . $dtoName;
        }

        $fields = [];
        $requiredFields = $input['required'] ?? [];

        foreach ($input['properties'] as $fieldName => $details) {
            if (str_starts_with($fieldName, '_')) {
                continue;
            }
            if (!is_array($details)) {
                continue;
            }

            // Resolve $ref to actual schema
            if (!empty($details['$ref'])) {
                $resolved = $this->resolveRef($details['$ref'], $options);
                if ($resolved === null) {
                    continue;
                }
                $details = $resolved;
            }

            $required = in_array($fieldName, $requiredFields, true);

            // Handle anyOf/oneOf type unions
            if (!isset($details['type']) && !empty($details['anyOf'])) {
                $details['type'] = $this->guessType($details['anyOf']);
                if (in_array('object', $details['type'], true)) {
                    $details = $this->detailsFromObject($details, 'anyOf');
                }
            }
            if (!isset($details['type']) && !empty($details['oneOf'])) {
                $details['type'] = $this->guessType($details['oneOf']);
                if (in_array('object', $details['type'], true)) {
                    $details = $this->detailsFromObject($details, 'oneOf');
                }
            }

            // Handle enum without explicit type
            if (!isset($details['type']) && !empty($details['enum'])) {
                $details['type'] = 'string';
            }

            // Handle array type in union - simplify to just 'array'
            // Only mark as optional if null was in the type array
            if (!empty($details['type']) && is_array($details['type']) && in_array('array', $details['type'], true)) {
                $hasNull = in_array('null', $details['type'], true);
                $details['type'] = 'array';
                if ($hasNull) {
                    $required = false;
                }
            }

            // Handle array of objects (collection) - including $ref in items
            if (!empty($details['type']) && $details['type'] === 'array' && !empty($details['items'])) {
                // Resolve $ref in items if present
                if (!empty($details['items']['$ref'])) {
                    $resolvedItems = $this->resolveRef($details['items']['$ref'], $options);
                    if ($resolvedItems !== null) {
                        $details['items'] = $resolvedItems;
                    }
                }

                if (!empty($details['items']['type']) && $details['items']['type'] === 'object') {
                    $details['collection'] = true;
                    $details['type'] = 'object';
                    $details['properties'] = $details['items']['properties'] ?? [];
                    $details['required'] = $details['items']['required'] ?? null;
                    if (!empty($details['items']['_resolvedRef'])) {
                        $details['_resolvedRef'] = $details['items']['_resolvedRef'];
                    }
                }
            }

            // Handle type arrays (union types with null)
            if (!empty($details['type']) && is_array($details['type'])) {
                // Remove null from type array and mark as optional
                if (in_array('null', $details['type'], true)) {
                    $details['type'] = array_values(array_filter($details['type'], fn ($t) => $t !== 'null'));
                    $required = false;
                }

                // Flatten nested arrays, normalize each type, and join with |
                $details['type'] = $this->flattenTypes($details['type']);
                $details['type'] = array_map(fn ($t) => $this->normalize($t), $details['type']);
                $type = implode('|', $details['type']);
                $details['type'] = $type;
            }

            if (!isset($details['type']) || $details['type'] === '') {
                $details['type'] = 'mixed';
            }

            $fieldName = Inflector::variable($fieldName);

            $fieldDetails = [
                'type' => $this->type($details['type']),
                'required' => $required,
            ];

            // Handle format specifiers (e.g., date-time, date, email)
            if (!empty($details['format'])) {
                $fieldDetails = $this->applyFormat($fieldDetails, $details['format']);
            }

            // Handle nested objects
            if ($fieldDetails['type'] === 'object') {
                // Check if this was a resolved $ref - use the pre-processed DTO name
                if (!empty($details['_resolvedRef'])) {
                    $fieldDetails['type'] = $details['_resolvedRef'];
                    if (!empty($details['collection'])) {
                        $singular = Inflector::singularize($fieldName);
                        if (!isset($fields[$singular])) {
                            $fieldDetails['singular'] = $singular;
                        }
                        $fieldDetails['type'] .= '[]';
                    }
                } else {
                    $parseDetails = ['dto' => $dtoName, 'field' => $fieldName, '_depth' => $parentData['_depth']];
                    if (!empty($details['collection'])) {
                        $parseDetails['collection'] = $details['collection'];
                    }
                    $this->parse($details, $options, $parseDetails);

                    if (isset($this->map[$dtoName][$fieldName])) {
                        $fieldDetails['type'] = $this->map[$dtoName][$fieldName];
                        if (!empty($details['collection'])) {
                            $singular = Inflector::singularize($fieldName);
                            // Skip on conflicting/existing field
                            if (!isset($this->map[$dtoName][$singular])) {
                                $fieldDetails['singular'] = $singular;
                            }
                            $dtoFields = $details['items']['properties'] ?? [];
                            $keyField = $this->detectKeyField($dtoFields);
                            if ($keyField) {
                                $fieldDetails['associative'] = $keyField;
                            }
                            $fieldDetails['type'] .= '[]';
                        }
                    }
                }
            }

            $fields[$fieldName] = $fieldDetails;
        }

        // Add extends if this DTO inherits from another
        if ($extends) {
            $fields['_extends'] = $extends;
        }

        $this->result[$dtoName] = $fields;
        if (!empty($parentData['dto']) && !empty($parentData['field'])) {
            /** @var string $parentDtoName */
            $parentDtoName = $parentData['dto'];
            /** @var string $parentFieldName */
            $parentFieldName = $parentData['field'];
            $this->map[$parentDtoName][$parentFieldName] = $dtoName;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function result(): array
    {
        return $this->result;
    }

    /**
     * Normalize type name.
     *
     * @param string $type
     *
     * @return string
     */
    protected function type(string $type): string
    {
        return $this->normalize($type);
    }

    /**
     * Normalize JSON Schema type names to DTO type names.
     *
     * @param string $name
     *
     * @return string
     */
    protected function normalize(string $name): string
    {
        return match ($name) {
            'boolean' => 'bool',
            'real', 'double', 'number' => 'float',
            'integer' => 'int',
            '[]' => 'array',
            default => $name,
        };
    }

    /**
     * Extract types from anyOf/oneOf array.
     *
     * @param array<int, array<string, mixed>> $anyOf
     *
     * @return array<string>
     */
    protected function guessType(array $anyOf): array
    {
        $types = [];
        foreach ($anyOf as $item) {
            if (isset($item['type'])) {
                $types[] = $item['type'];
            }
        }

        return $types;
    }

    /**
     * Extract object details from anyOf/oneOf.
     *
     * @param array<string, mixed> $details
     * @param string $key 'anyOf' or 'oneOf'
     *
     * @return array<string, mixed>
     */
    protected function detailsFromObject(array $details, string $key): array
    {
        if (empty($details[$key])) {
            return $details;
        }

        foreach ($details[$key] as $item) {
            if (empty($item['type'])) {
                continue;
            }

            if ($item['type'] !== 'object') {
                continue;
            }

            $details['properties'] = $item['properties'] ?? [];
            $details['required'] = $item['required'] ?? null;
            $details['title'] = $item['title'] ?? null;
        }

        return $details;
    }

    /**
     * Flatten nested type arrays.
     *
     * @param array<mixed> $types
     *
     * @return array<string>
     */
    protected function flattenTypes(array $types): array
    {
        $result = [];
        foreach ($types as $type) {
            if (is_array($type)) {
                $result = array_merge($result, $this->flattenTypes($type));
            } else {
                $result[] = $type;
            }
        }

        return $result;
    }

    /**
     * Detect potential key field for associative arrays.
     *
     * @param array<string, mixed> $dtoFields
     *
     * @return string|null
     */
    protected function detectKeyField(array $dtoFields): ?string
    {
        $strings = Config::keyFields();
        foreach ($strings as $string) {
            if (!empty($dtoFields[$string]) && !empty($dtoFields[$string]['type']) && $dtoFields[$string]['type'] === 'string') {
                return $string;
            }
        }

        return null;
    }

    /**
     * Extract definitions from $defs, definitions, or components/schemas.
     *
     * @param array<string, mixed> $schema
     *
     * @return void
     */
    protected function extractDefinitions(array $schema): void
    {
        // JSON Schema draft-07+ uses $defs
        if (!empty($schema['$defs'])) {
            $this->definitions = array_merge($this->definitions, $schema['$defs']);
        }

        // Older JSON Schema uses definitions
        if (!empty($schema['definitions'])) {
            $this->definitions = array_merge($this->definitions, $schema['definitions']);
        }

        // OpenAPI 3.x uses components/schemas
        if (!empty($schema['components']['schemas'])) {
            $this->definitions = array_merge($this->definitions, $schema['components']['schemas']);
        }
    }

    /**
     * Resolve a $ref pointer to its schema definition.
     *
     * Supports:
     * - #/$defs/Name
     * - #/definitions/Name
     * - #/components/schemas/Name
     *
     * @param string $ref The $ref pointer
     * @param array<string, mixed> $options Parser options
     *
     * @return array<string, mixed>|null The resolved schema or null if not found
     */
    protected function resolveRef(string $ref, array $options): ?array
    {
        // Local references (same file)
        if (str_starts_with($ref, '#/')) {
            $name = $this->extractRefName($ref);
            if ($name === null || !isset($this->definitions[$name])) {
                return null;
            }

            return $this->resolveSchemaReference($this->definitions[$name], $name, $options, $name);
        }

        if ($this->refResolver === null) {
            return null;
        }

        $resolved = $this->refResolver->resolve($ref, $options);
        if ($resolved === null || empty($resolved['schema']) || !is_array($resolved['schema'])) {
            return null;
        }

        if (!empty($resolved['definitionsSource']) && is_array($resolved['definitionsSource'])) {
            $this->extractDefinitions($resolved['definitionsSource']);
        }

        $name = $this->extractExternalRefName($ref, $resolved['schema']);

        return $this->resolveSchemaReference($resolved['schema'], $name, $options, $ref);
    }

    /**
     * Extract the definition name from a $ref pointer.
     *
     * @param string $ref The $ref pointer
     *
     * @return string|null The definition name or null if invalid
     */
    protected function extractRefName(string $ref): ?string
    {
        $patterns = [
            '~^#/\\$defs/(.+)$~' => 1,
            '~^#/definitions/(.+)$~' => 1,
            '~^#/components/schemas/(.+)$~' => 1,
        ];

        foreach ($patterns as $pattern => $group) {
            if (preg_match($pattern, $ref, $matches)) {
                return $matches[$group];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $schema
     * @param string|null $name
     * @param array<string, mixed> $options
     * @param string $refKey
     *
     * @return array<string, mixed>|null
     */
    protected function resolveSchemaReference(array $schema, ?string $name, array $options, string $refKey): ?array
    {
        // If this is an object type, parse it as a separate DTO
        if (!empty($schema['type']) && $schema['type'] === 'object' && !empty($schema['properties'])) {
            $title = $schema['title'] ?? null;
            if ($title === null) {
                $title = $name ?? $this->fallbackTitleFromRef($refKey);
                $schema['title'] = $title;
            }

            if (!isset($this->processedRefs[$refKey])) {
                $this->processedRefs[$refKey] = Inflector::camelize($title);
                $this->parse($schema, $options, ['dto' => '__ref__', 'field' => $title]);
            }

            return [
                'type' => 'object',
                'properties' => $schema['properties'],
                'required' => $schema['required'] ?? [],
                'title' => $schema['title'],
                '_resolvedRef' => $this->processedRefs[$refKey],
            ];
        }

        return $schema;
    }

    /**
     * @param string $ref
     * @param array<string, mixed> $schema
     *
     * @return string|null
     */
    protected function extractExternalRefName(string $ref, array $schema): ?string
    {
        $fragment = $this->extractRefFragment($ref);
        if ($fragment !== null) {
            $name = $this->extractRefName($fragment);
            if ($name !== null) {
                return $name;
            }
        }

        if (!empty($schema['title']) && is_string($schema['title'])) {
            return $schema['title'];
        }

        $path = parse_url($ref, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $baseName = pathinfo($path, PATHINFO_FILENAME);

        return $baseName !== '' ? $baseName : null;
    }

    /**
     * @param string $ref
     *
     * @return string|null
     */
    protected function extractRefFragment(string $ref): ?string
    {
        $parts = explode('#', $ref, 2);
        if (count($parts) !== 2) {
            return null;
        }

        return '#' . $parts[1];
    }

    /**
     * @param string $ref
     *
     * @return string
     */
    protected function fallbackTitleFromRef(string $ref): string
    {
        $path = parse_url($ref, PHP_URL_PATH) ?? $ref;
        $baseName = pathinfo($path, PATHINFO_FILENAME);

        return $baseName !== '' ? $baseName : 'Object';
    }

    /**
     * Check if the input is an OpenAPI document.
     *
     * @param array<string, mixed> $input
     *
     * @return bool
     */
    protected function isOpenApi(array $input): bool
    {
        return !empty($input['openapi']) && !empty($input['components']['schemas']);
    }

    /**
     * Parse all schemas from an OpenAPI document's components/schemas.
     *
     * @param array<string, mixed> $input The OpenAPI document
     * @param array<string, mixed> $options Parser options
     *
     * @return static
     */
    protected function parseOpenApiSchemas(array $input, array $options): static
    {
        $schemas = $input['components']['schemas'] ?? [];

        foreach ($schemas as $name => $schema) {
            // Skip if not an object type or already processed
            if (empty($schema['type']) || $schema['type'] !== 'object') {
                continue;
            }
            if (isset($this->processedRefs[$name])) {
                continue;
            }

            // Use schema name as title if not set
            if (empty($schema['title'])) {
                $schema['title'] = $name;
            }

            // Mark as processed and parse
            $this->processedRefs[$name] = Inflector::camelize($schema['title']);
            $this->parse($schema, $options, ['dto' => '__openapi__', 'field' => $name]);
        }

        return $this;
    }

    /**
     * Process allOf composition to merge schemas and detect inheritance.
     *
     * @param array<string, mixed> $input The schema with allOf
     * @param array<string, mixed> $options Parser options
     *
     * @return array{0: array<string, mixed>, 1: string|null} Merged schema and parent DTO name
     */
    protected function processAllOf(array $input, array $options): array
    {
        $extends = null;
        $mergedProperties = [];
        $mergedRequired = [];
        $title = $input['title'] ?? null;

        foreach ($input['allOf'] as $schema) {
            // Handle $ref - this indicates inheritance
            if (!empty($schema['$ref'])) {
                $resolved = $this->resolveRef($schema['$ref'], $options);
                if ($resolved !== null) {
                    // Get the parent DTO name
                    $extends = $resolved['_resolvedRef'] ?? null;

                    // Optionally merge parent properties (for complete DTO)
                    // Skip this to only include own properties in child DTO
                }

                continue;
            }

            // Merge properties from this schema
            if (!empty($schema['properties'])) {
                $mergedProperties = array_merge($mergedProperties, $schema['properties']);
            }

            // Merge required fields
            if (!empty($schema['required'])) {
                $mergedRequired = array_merge($mergedRequired, $schema['required']);
            }

            // Use title from inline schema if not set
            if (!$title && !empty($schema['title'])) {
                $title = $schema['title'];
            }
        }

        // Build merged schema
        $merged = [
            'type' => 'object',
            'properties' => $mergedProperties,
        ];

        if ($title) {
            $merged['title'] = $title;
        }

        if ($mergedRequired) {
            $merged['required'] = array_unique($mergedRequired);
        }

        return [$merged, $extends];
    }

    /**
     * Apply format specifier to field details.
     *
     * Maps JSON Schema format values to appropriate DTO types:
     * - date-time, date → \DateTimeInterface class
     * - Other formats remain as their base type (string)
     *
     * @param array<string, mixed> $fieldDetails
     * @param string $format
     *
     * @return array<string, mixed>
     */
    protected function applyFormat(array $fieldDetails, string $format): array
    {
        // Only apply format mapping to string types
        if ($fieldDetails['type'] !== 'string') {
            return $fieldDetails;
        }

        return match ($format) {
            'date-time', 'date' => array_merge($fieldDetails, ['type' => '\\DateTimeInterface']),
            default => $fieldDetails,
        };
    }
}
