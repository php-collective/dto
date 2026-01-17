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
     * @param array<string, array<string, mixed>|string> $fields
     *
     * @return string
     */
    protected function buildXml(string $name, array $fields): string
    {
        // Extract extends if present
        /** @var string|null $extends */
        $extends = isset($fields['_extends']) && is_string($fields['_extends']) ? $fields['_extends'] : null;
        unset($fields['_extends']);

        $fieldLines = [];
        foreach ($fields as $fieldName => $fieldDetails) {
            if (!is_array($fieldDetails)) {
                continue;
            }
            if (isset($fieldDetails['_include']) && !$fieldDetails['_include']) {
                continue;
            }

            $attr = [
                'name="' . $this->escapeXml($fieldName) . '"',
                'type="' . $this->escapeXml((string)($fieldDetails['type'] ?? 'mixed')) . '"',
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
        $extendsAttr = $extends ? ' extends="' . $this->escapeXml($extends) . '"' : '';

        return <<<XML
	<dto name="{$name}"{$extendsAttr}>
{$fieldsStr}
	</dto>
XML;
    }

    /**
     * Build XML output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>|string>> $definitions
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
<dtos xmlns="cakephp-dto">
{$dtosStr}
</dtos>
XML;
    }

    /**
     * Build PHP output for a single DTO.
     *
     * @param string $name
     * @param array<string, array<string, mixed>|string> $fields
     *
     * @return string
     */
    protected function buildPhp(string $name, array $fields): string
    {
        // Extract extends if present
        /** @var string|null $extends */
        $extends = isset($fields['_extends']) && is_string($fields['_extends']) ? $fields['_extends'] : null;
        unset($fields['_extends']);

        $fieldLines = [];
        foreach ($fields as $fieldName => $fieldDetails) {
            if (!is_array($fieldDetails)) {
                continue;
            }
            if (isset($fieldDetails['_include']) && !$fieldDetails['_include']) {
                continue;
            }

            $type = $fieldDetails['type'] ?? 'mixed';
            $required = !empty($fieldDetails['required']);

            // Build the Field:: call based on type
            $line = $this->buildFieldPhp($fieldName, $type);

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
        $extendsStr = $extends ? "->extends('{$extends}')" : '';

        return <<<PHP
        Dto::create('{$name}'){$extendsStr}->fields(
{$fieldsStr}
        ),
PHP;
    }

    /**
     * Build a Field:: method call for PHP output.
     *
     * @param string $fieldName
     * @param string $type
     *
     * @return string
     */
    protected function buildFieldPhp(string $fieldName, string $type): string
    {
        $indent = '            ';

        // Handle empty type
        if ($type === '') {
            return "{$indent}Field::mixed('{$fieldName}')";
        }

        // Handle collection types (ending with [])
        if (str_ends_with($type, '[]')) {
            $baseType = substr($type, 0, -2);
            // If it's a DTO type (starts with uppercase or contains /), use collection()
            if ($baseType !== '' && (ctype_upper($baseType[0]) || str_contains($baseType, '/'))) {
                return "{$indent}Field::collection('{$fieldName}', '{$baseType}')";
            }

            // Scalar arrays (including empty baseType which becomes generic array)
            return $baseType !== '' ? "{$indent}Field::array('{$fieldName}', '{$baseType}')" : "{$indent}Field::array('{$fieldName}')";
        }

        // Handle single DTO types (starts with uppercase or contains /)
        if (ctype_upper($type[0]) || str_contains($type, '/')) {
            return "{$indent}Field::dto('{$fieldName}', '{$type}')";
        }

        // Handle scalar types
        return match ($type) {
            'string' => "{$indent}Field::string('{$fieldName}')",
            'int' => "{$indent}Field::int('{$fieldName}')",
            'float' => "{$indent}Field::float('{$fieldName}')",
            'bool' => "{$indent}Field::bool('{$fieldName}')",
            'array' => "{$indent}Field::array('{$fieldName}')",
            'mixed' => "{$indent}Field::mixed('{$fieldName}')",
            default => "{$indent}Field::of('{$fieldName}', '{$type}')",
        };
    }

    /**
     * Build PHP output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>|string>> $definitions
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
     * @param array<string, array<string, mixed>|string> $fields
     *
     * @return string
     */
    protected function buildYaml(string $name, array $fields): string
    {
        // Extract extends if present
        /** @var string|null $extends */
        $extends = isset($fields['_extends']) && is_string($fields['_extends']) ? $fields['_extends'] : null;
        unset($fields['_extends']);

        $lines = ["{$name}:"];

        if ($extends) {
            $lines[] = "  extends: {$extends}";
        }

        $lines[] = '  fields:';

        foreach ($fields as $fieldName => $fieldDetails) {
            if (!is_array($fieldDetails)) {
                continue;
            }
            if (isset($fieldDetails['_include']) && !$fieldDetails['_include']) {
                continue;
            }

            $type = $fieldDetails['type'] ?? 'mixed';
            $hasOptions = !empty($fieldDetails['required']) ||
                !empty($fieldDetails['singular']) ||
                !empty($fieldDetails['associative']);

            if ($hasOptions) {
                $lines[] = "    {$fieldName}:";
                $lines[] = "      type: {$type}";

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
            } else {
                $lines[] = "    {$fieldName}: {$type}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build YAML output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>|string>> $definitions
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
     * @param array<string, array<string, mixed>|string> $fields
     *
     * @return string
     */
    protected function buildNeon(string $name, array $fields): string
    {
        // Extract extends if present
        /** @var string|null $extends */
        $extends = isset($fields['_extends']) && is_string($fields['_extends']) ? $fields['_extends'] : null;
        unset($fields['_extends']);

        $lines = ["{$name}:"];

        if ($extends) {
            $lines[] = "\textends: {$extends}";
        }

        $lines[] = "\tfields:";

        foreach ($fields as $fieldName => $fieldDetails) {
            if (!is_array($fieldDetails)) {
                continue;
            }
            if (isset($fieldDetails['_include']) && !$fieldDetails['_include']) {
                continue;
            }

            $type = $fieldDetails['type'] ?? 'mixed';
            $hasOptions = !empty($fieldDetails['required']) ||
                !empty($fieldDetails['singular']) ||
                !empty($fieldDetails['associative']);

            if ($hasOptions) {
                $lines[] = "\t\t{$fieldName}:";
                $lines[] = "\t\t\ttype: " . $this->quoteNeonValue($type);

                if (!empty($fieldDetails['required'])) {
                    $lines[] = "\t\t\trequired: true";
                }
                if (!empty($fieldDetails['singular'])) {
                    $lines[] = "\t\t\tsingular: {$fieldDetails['singular']}";
                }
                if (!empty($fieldDetails['associative'])) {
                    $lines[] = "\t\t\tassociative: true";
                    $lines[] = "\t\t\tkey: {$fieldDetails['associative']}";
                }
            } else {
                $lines[] = "\t\t{$fieldName}: " . $this->quoteNeonValue($type);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Build NEON output for all DTOs.
     *
     * @param array<string, array<string, array<string, mixed>|string>> $definitions
     *
     * @return string
     */
    protected function buildAllNeon(array $definitions): string
    {
        $dtos = [];
        foreach ($definitions as $name => $fields) {
            $dtos[] = $this->buildNeon($name, $fields);
        }

        return implode("\n\n", $dtos) . "\n";
    }

    /**
     * Quote NEON value if it contains special characters.
     *
     * @param string $value
     *
     * @return string
     */
    protected function quoteNeonValue(string $value): string
    {
        if (preg_match('/[\[\]\/\\\\]/', $value)) {
            return "'{$value}'";
        }

        return $value;
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
