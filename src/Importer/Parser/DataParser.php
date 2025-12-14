<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Parser;

use PhpCollective\Dto\Utility\Inflector;

/**
 * Parser that infers DTO schema from example JSON data.
 */
class DataParser implements ParserInterface
{
    /**
     * @var string
     */
    public const NAME = 'Data';

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $result = [];

    /**
     * @var array<string, array<string, string>>
     */
    protected array $map = [];

    /**
     * @inheritDoc
     */
    public function parse(array $input, array $options = [], array $parentData = []): static
    {
        $dtoName = 'Object';
        if ($parentData) {
            $field = $parentData['field'];
            if (!empty($parentData['collection'])) {
                $field = Inflector::singularize($field);
            }
            $dtoName = ucfirst($field);
        }

        if (!empty($options['namespace'])) {
            $dtoName = rtrim($options['namespace'], '/') . '/' . $dtoName;
        }

        $fields = [];

        foreach ($input as $fieldName => $value) {
            $fieldDetails = [
                'value' => $value,
            ];

            // Skip private/internal fields
            if (str_starts_with((string)$fieldName, '_')) {
                continue;
            }

            $fieldName = Inflector::variable((string)$fieldName);

            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    // Nested object
                    $parseDetails = ['dto' => $dtoName, 'field' => $fieldName];
                    $this->parse($value, $options, $parseDetails);
                } elseif ($this->isNumericKeyed($value) && $this->hasAssocValues($value)) {
                    // Array of objects (collection)
                    $parseDetails = ['dto' => $dtoName, 'field' => $fieldName];
                    $parseDetails['collection'] = true;

                    $this->parse($value[0], $options, $parseDetails);
                    $fieldDetails['collection'] = true;
                }
            }

            $type = $this->type($value);
            if (!empty($fieldDetails['collection'])) {
                $type = 'object';
            }

            $fieldDetails['type'] = $type;

            if (isset($this->map[$dtoName][$fieldName])) {
                $fieldDetails['type'] = $this->map[$dtoName][$fieldName];
                if (!empty($fieldDetails['collection'])) {
                    $singular = Inflector::singularize($fieldName);
                    // Skip on conflicting/existing field
                    if (!isset($this->map[$dtoName][$singular])) {
                        $fieldDetails['singular'] = $singular;
                    }

                    $keyField = $this->detectKeyField($value[0] ?? []);
                    if ($keyField) {
                        $fieldDetails['associative'] = $keyField;
                    }
                    $fieldDetails['type'] .= '[]';
                }
            }

            // Remove internal value key for final output
            unset($fieldDetails['value']);

            $fields[$fieldName] = $fieldDetails;
        }

        $this->result[$dtoName] = $fields;
        if ($parentData) {
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
     * Infer type from value.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function type(mixed $value): string
    {
        $type = gettype($value);

        if ($type === 'NULL') {
            return 'mixed';
        }

        return $this->normalize($type);
    }

    /**
     * Normalize PHP type names to DTO type names.
     *
     * @param string $name
     *
     * @return string
     */
    protected function normalize(string $name): string
    {
        return match ($name) {
            'boolean' => 'bool',
            'real', 'double' => 'float',
            'integer' => 'int',
            '[]' => 'array',
            default => $name,
        };
    }

    /**
     * Check if array is associative (object-like).
     *
     * @param array<mixed> $value
     *
     * @return bool
     */
    protected function isAssoc(array $value): bool
    {
        foreach ($value as $k => $v) {
            if (!is_string($k)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if array has numeric keys (list-like).
     *
     * @param array<mixed> $value
     *
     * @return bool
     */
    protected function isNumericKeyed(array $value): bool
    {
        foreach ($value as $k => $v) {
            if (!is_int($k)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if array contains associative arrays.
     *
     * @param array<mixed> $value
     *
     * @return bool
     */
    protected function hasAssocValues(array $value): bool
    {
        foreach ($value as $v) {
            if (!is_array($v) || !$this->isAssoc($v)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detect potential key field for associative arrays.
     *
     * @param array<string, mixed> $value
     *
     * @return string|null
     */
    protected function detectKeyField(array $value): ?string
    {
        $strings = Config::keyFields();
        foreach ($strings as $string) {
            if (isset($value[$string]) && is_string($value[$string])) {
                return $string;
            }
        }

        return null;
    }
}
