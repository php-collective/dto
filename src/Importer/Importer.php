<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer;

use PhpCollective\Dto\Importer\Builder\SchemaBuilder;
use PhpCollective\Dto\Importer\Parser\DataParser;
use PhpCollective\Dto\Importer\Parser\SchemaParser;

/**
 * Main importer class for generating DTO schemas from JSON data or JSON Schema.
 *
 * Usage:
 *
 * ```php
 * $importer = new Importer();
 *
 * // From JSON data example
 * $json = '{"name": "John", "age": 30}';
 * $result = $importer->parse($json);
 * echo $importer->buildSchema($result);
 *
 * // From JSON Schema
 * $schema = file_get_contents('schema.json');
 * $result = $importer->parse($schema, ['type' => 'Schema']);
 * echo $importer->buildSchema($result, ['format' => 'xml']);
 * ```
 */
class Importer
{
    /**
     * Parse JSON input and return DTO definitions.
     *
     * Auto-detects whether input is JSON data or JSON Schema.
     *
     * @param string $json JSON string to parse
     * @param array<string, mixed> $options Options:
     *   - type: 'Data' or 'Schema' (auto-detected if not provided)
     *   - namespace: Namespace prefix for generated DTOs
     *   - basePath: Base path for external $ref file resolution
     *   - refResolver: Custom ref resolver instance
     *
     * @return array<string, array<string, array<string, mixed>|string>> Parsed DTO definitions
     */
    public function parse(string $json, array $options = []): array
    {
        $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!$array) {
            return [];
        }

        if (empty($options['type'])) {
            $options['type'] = $this->guessType($array);
        }

        return ParserFactory::create($options['type'])->parse($array, $options)->result();
    }

    /**
     * Parse array input and return DTO definitions.
     *
     * @param array<string, mixed> $data Data array to parse
     * @param array<string, mixed> $options Same options as parse()
     *
     * @return array<string, array<string, array<string, mixed>|string>> Parsed DTO definitions
     */
    public function parseArray(array $data, array $options = []): array
    {
        if (!$data) {
            return [];
        }

        if (empty($options['type'])) {
            $options['type'] = $this->guessType($data);
        }

        return ParserFactory::create($options['type'])->parse($data, $options)->result();
    }

    /**
     * Build DTO schema configuration from parsed definitions.
     *
     * @param array<string, array<string, array<string, mixed>|string>> $definitions Parsed definitions from parse()
     * @param array<string, mixed> $options Options:
     *   - format: 'php' (default), 'xml', 'yaml', or 'neon'
     *
     * @return string Generated schema configuration
     */
    public function buildSchema(array $definitions, array $options = []): string
    {
        $builder = new SchemaBuilder();

        return $builder->buildAll($definitions, $options);
    }

    /**
     * Convenience method to parse JSON and build schema in one call.
     *
     * @param string $json JSON string to parse
     * @param array<string, mixed> $options Combined options for parse() and buildSchema()
     *
     * @return string Generated schema configuration
     */
    public function import(string $json, array $options = []): string
    {
        $definitions = $this->parse($json, $options);

        return $this->buildSchema($definitions, $options);
    }

    /**
     * Convenience method to parse array and build schema in one call.
     *
     * @param array<string, mixed> $data Data array to parse
     * @param array<string, mixed> $options Combined options for parseArray() and buildSchema()
     *
     * @return string Generated schema configuration
     */
    public function importArray(array $data, array $options = []): string
    {
        $definitions = $this->parseArray($data, $options);

        return $this->buildSchema($definitions, $options);
    }

    /**
     * Guess whether input is JSON Schema or plain data.
     *
     * @param array<string, mixed> $array
     *
     * @return string Parser type name
     */
    protected function guessType(array $array): string
    {
        // OpenAPI 3.x documents have an 'openapi' key with version string
        if (!empty($array['openapi']) && !empty($array['components']['schemas'])) {
            return SchemaParser::NAME;
        }

        // JSON Schema typically has type: object and properties
        if (!empty($array['type']) && $array['type'] === 'object' && !empty($array['properties'])) {
            return SchemaParser::NAME;
        }

        // JSON Schema with allOf composition
        if (!empty($array['allOf'])) {
            return SchemaParser::NAME;
        }

        // Check for $schema which is common in JSON Schema
        if (!empty($array['$schema'])) {
            return SchemaParser::NAME;
        }

        return DataParser::NAME;
    }
}
