<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Builder;

/**
 * Builds DTO schema configuration in various formats.
 */
class SchemaBuilder implements BuilderInterface
{
    /**
     * @var string
     */
    public const FORMAT_XML = 'xml';

    /**
     * @var string
     */
    public const FORMAT_PHP = 'php';

    /**
     * @var string
     */
    public const FORMAT_YAML = 'yaml';

    /**
     * @var string
     */
    public const FORMAT_NEON = 'neon';

    /**
     * @inheritDoc
     */
    public function build(string $name, array $fields, array $options = []): string
    {
        $format = $options['format'] ?? self::FORMAT_PHP;

        return match ($format) {
            self::FORMAT_XML => $this->buildXml($name, $fields),
            self::FORMAT_YAML => $this->buildYaml($name, $fields),
            self::FORMAT_NEON => $this->buildNeon($name, $fields),
            default => $this->buildPhp($name, $fields),
        };
    }

    /**
     * @inheritDoc
     */
    public function buildAll(array $definitions, array $options = []): string
    {
        $format = $options['format'] ?? self::FORMAT_PHP;

        // Filter out excluded definitions
        $filtered = [];
        foreach ($definitions as $name => $fields) {
            if (isset($fields['_include']) && !$fields['_include']) {
                continue;
            }
            unset($fields['_include']);
            $filtered[$name] = $fields;
        }

        return match ($format) {
            self::FORMAT_XML => $this->buildAllXml($filtered),
            self::FORMAT_YAML => $this->buildAllYaml($filtered),
            self::FORMAT_NEON => $this->buildAllNeon($filtered),
            default => $this->buildAllPhp($filtered),
        };
    }

    /**
     * Build XML output for a single DTO.
     *
     * @param string $name
     * @param array<string, array<string, mixed>> $fields
     *
     * @return string
     */
    protected function buildXml(string $name, array $fields): string
    {
        $fieldLines = [];
        foreach ($fields as $fieldName => $fieldDetails) {
            if (isset($fieldDetails['_include']) && !$fieldDetails['_include']) {
                continue;
            }

            $attr = [
                'name="' . $this->escapeXml($fieldName) . '"',
                'type="' . $this->escapeXml($fieldDetails['type'] ?? 'mixed') . '"',
            ];

            if (!empty($fieldDetails['required'])) {
                $attr[] = 'required="true"';
            }
            if (!empty($fieldDetails['singular'])) {
                $attr[] = 'singular="' . $this->escapeXml($fieldDetails['singular']) . '"';
            }
            if (!empty($fieldDetails['associative'])) {
                $attr[] = 'associative="true"';
                $attr[] = 'key="' . $this->escapeXml($fieldDetails['associative']) . '"';
            }

            $fieldLines[] = "\t\t" . '<field ' . implode(' ', $attr) . '/>';
        }

        $fieldsStr = implode("\n", $fieldLines);

        return <<<XML
	<dto name="{$name}">
{$fieldsStr}
	</dto>
XML;
    }

    /**
     * Build XML output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>>> $definitions
     *
     * @return string
     */
    protected function buildAllXml(array $definitions): string
    {
        $dtos = [];
        foreach ($definitions as $name => $fields) {
            $dtos[] = $this->buildXml($name, $fields);
        }

        $dtosStr = implode("\n", $dtos);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="https://php-collective.github.io/dto/schema">
{$dtosStr}
</dtos>
XML;
    }

    /**
     * Build PHP output for a single DTO.
     *
     * @param string $name
     * @param array<string, array<string, mixed>> $fields
     *
     * @return string
     */
    protected function buildPhp(string $name, array $fields): string
    {
        $fieldLines = [];
        foreach ($fields as $fieldName => $fieldDetails) {
            if (isset($fieldDetails['_include']) && !$fieldDetails['_include']) {
                continue;
            }

            $type = $fieldDetails['type'] ?? 'mixed';
            $required = !empty($fieldDetails['required']);

            // Determine the Field:: method to use
            $method = $this->getFieldMethod($type);
            $line = "            Field::{$method}('{$fieldName}')";

            if ($required) {
                $line .= '->required()';
            }

            if (!empty($fieldDetails['singular'])) {
                $line .= "->singular('{$fieldDetails['singular']}')";
            }
            if (!empty($fieldDetails['associative'])) {
                $line .= "->associative('{$fieldDetails['associative']}')";
            }

            $fieldLines[] = $line . ',';
        }

        $fieldsStr = implode("\n", $fieldLines);

        return <<<PHP
        Dto::create('{$name}')->fields(
{$fieldsStr}
        ),
PHP;
    }

    /**
     * Build PHP output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>>> $definitions
     *
     * @return string
     */
    protected function buildAllPhp(array $definitions): string
    {
        $dtos = [];
        foreach ($definitions as $name => $fields) {
            $dtos[] = $this->buildPhp($name, $fields);
        }

        $dtosStr = implode("\n", $dtos);

        return <<<PHP
<?php

use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\DtoBuilder;
use PhpCollective\Dto\Config\Field;

return DtoBuilder::create()
    ->dtos(
{$dtosStr}
    )
    ->build();
PHP;
    }

    /**
     * Build YAML output for a single DTO.
     *
     * @param string $name
     * @param array<string, array<string, mixed>> $fields
     *
     * @return string
     */
    protected function buildYaml(string $name, array $fields): string
    {
        $lines = ["{$name}:"];
        $lines[] = '  fields:';

        foreach ($fields as $fieldName => $fieldDetails) {
            if (isset($fieldDetails['_include']) && !$fieldDetails['_include']) {
                continue;
            }

            $lines[] = "    {$fieldName}:";
            $lines[] = "      type: {$fieldDetails['type']}";

            if (!empty($fieldDetails['required'])) {
                $lines[] = '      required: true';
            }
            if (!empty($fieldDetails['singular'])) {
                $lines[] = "      singular: {$fieldDetails['singular']}";
            }
            if (!empty($fieldDetails['associative'])) {
                $lines[] = '      associative: true';
                $lines[] = "      key: {$fieldDetails['associative']}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build YAML output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>>> $definitions
     *
     * @return string
     */
    protected function buildAllYaml(array $definitions): string
    {
        $dtos = [];
        foreach ($definitions as $name => $fields) {
            $dtos[] = $this->buildYaml($name, $fields);
        }

        return implode("\n\n", $dtos) . "\n";
    }

    /**
     * Build NEON output for a single DTO.
     *
     * @param string $name
     * @param array<string, array<string, mixed>> $fields
     *
     * @return string
     */
    protected function buildNeon(string $name, array $fields): string
    {
        // NEON format is similar to YAML
        return $this->buildYaml($name, $fields);
    }

    /**
     * Build NEON output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>>> $definitions
     *
     * @return string
     */
    protected function buildAllNeon(array $definitions): string
    {
        return $this->buildAllYaml($definitions);
    }

    /**
     * Get the Field:: method name for a type.
     *
     * @param string $type
     *
     * @return string
     */
    protected function getFieldMethod(string $type): string
    {
        // Handle collection types
        if (str_ends_with($type, '[]')) {
            $baseType = substr($type, 0, -2);
            // If it's a DTO type (starts with uppercase), use dtos()
            if (ctype_upper($baseType[0])) {
                return "dtos('{$baseType}')";
            }

            return match ($baseType) {
                'string' => 'strings',
                'int' => 'ints',
                'float' => 'floats',
                'bool' => 'bools',
                default => "collection('{$type}')",
            };
        }

        // Handle DTO types (starts with uppercase)
        if (ctype_upper($type[0])) {
            return "dto('{$type}')";
        }

        return match ($type) {
            'string' => 'string',
            'int' => 'int',
            'float' => 'float',
            'bool' => 'bool',
            'array' => 'array',
            'mixed' => 'mixed',
            default => "field('{$type}')",
        };
    }

    /**
     * Escape special characters for XML.
     *
     * @param string $value
     *
     * @return string
     */
    protected function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
