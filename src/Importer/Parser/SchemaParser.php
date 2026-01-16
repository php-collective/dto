<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Parser;

use PhpCollective\Dto\Utility\Inflector;

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
     * @var array<string, array<string, array<string, mixed>>>
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
     * @inheritDoc
     */
    public function parse(array $input, array $options = [], array $parentData = []): static
    {
        // Extract definitions on first call (no parent data)
        if (!$parentData) {
            $this->extractDefinitions($input);
        }

        if (!$input || empty($input['properties'])) {
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

            // Handle array type in union
            if (!empty($details['type']) && is_array($details['type']) && in_array('array', $details['type'], true)) {
                $details['type'] = 'array';
                $required = false;
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
                    $parseDetails = ['dto' => $dtoName, 'field' => $fieldName];
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

        $this->result[$dtoName] = $fields;
        if ($parentData) {
            /** @var string $parentDtoName */
            $parentDtoName = $parentData['dto'] ?? null;
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
        // Only support local references (same file)
        if (!str_starts_with($ref, '#/')) {
            return null;
        }

        // Extract the definition name from the pointer
        $name = $this->extractRefName($ref);
        if ($name === null || !isset($this->definitions[$name])) {
            return null;
        }

        $schema = $this->definitions[$name];

        // If this is an object type, parse it as a separate DTO
        if (!empty($schema['type']) && $schema['type'] === 'object' && !empty($schema['properties'])) {
            // Use the definition name as title if not set
            if (empty($schema['title'])) {
                $schema['title'] = $name;
            }

            // Only process each ref once to avoid duplicates
            if (!isset($this->processedRefs[$name])) {
                $this->processedRefs[$name] = Inflector::camelize($schema['title']);
                $this->parse($schema, $options, ['dto' => '__ref__', 'field' => $name]);
            }

            // Return a schema that references the DTO type
            return [
                'type' => 'object',
                'properties' => $schema['properties'],
                'required' => $schema['required'] ?? [],
                'title' => $schema['title'],
                '_resolvedRef' => $this->processedRefs[$name],
            ];
        }

        // For non-object types, just return the schema
        return $schema;
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
}
