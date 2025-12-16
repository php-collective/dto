<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Builds PHPDoc array shape types for DTOs.
 */
class ArrayShapeBuilder
{
    /**
     * @var string
     */
    protected string $suffix;

    /**
     * @param string $suffix
     */
    public function __construct(string $suffix = 'Dto')
    {
        $this->suffix = $suffix;
    }

    /**
     * Build a shaped array type for PHPDoc annotations on toArray()/createFromArray().
     *
     * Generates types like: array{name: string, count: int, items: array<int, ItemDto>}
     *
     * When a DTO extends another DTO, the parent's fields are included first to ensure
     * the child's return type is covariant (compatible with LSP).
     *
     * @param array<string, array<string, mixed>> $fields
     * @param array<string, array<string, mixed>> $allDtos All DTOs for resolving nested shapes
     * @param array<string, mixed>|null $dto The current DTO definition (for inheritance)
     *
     * @return string
     */
    public function buildArrayShape(array $fields, array $allDtos = [], ?array $dto = null): string
    {
        $allFields = [];

        // If DTO extends another DTO, include parent fields first (for LSP covariance)
        if ($dto !== null && !empty($dto['extends'])) {
            $parentDtoName = $this->extractDtoName($dto['extends']);
            if ($parentDtoName && isset($allDtos[$parentDtoName])) {
                $parentDto = $allDtos[$parentDtoName];
                // Recursively get parent fields (handles multi-level inheritance)
                $parentFields = $this->collectInheritedFields($parentDto, $allDtos);
                $allFields = $parentFields;
            }
        }

        // Add/override with current DTO's fields
        foreach ($fields as $name => $field) {
            $allFields[$name] = $field;
        }

        $parts = [];
        foreach ($allFields as $name => $field) {
            $type = $this->buildFieldShapeType($field, $allDtos);
            $parts[] = $name . ': ' . $type;
        }

        return 'array{' . implode(', ', $parts) . '}';
    }

    /**
     * Build a generic PHPDoc type for array collections.
     *
     * Converts `string[]` to `array<int, string>`.
     *
     * @param array<string, mixed> $field
     *
     * @return string
     */
    public function buildGenericArrayType(array $field): string
    {
        $elementType = $field['singularType'] ?? null;

        // Extract element type from type[] notation if not already set
        if (!$elementType && isset($field['type'])) {
            $type = $field['type'];
            if (str_ends_with($type, '[]')) {
                $elementType = substr($type, 0, -2);
            }
        }

        // Include nullable in element type if singularNullable is set
        if (!empty($field['singularNullable']) && $elementType) {
            $elementType .= '|null';
        }

        $keyType = ($field['associative'] ?? false) ? 'string' : 'int';

        return sprintf('array<%s, %s>', $keyType, $elementType ?: 'mixed');
    }

    /**
     * Build a generic PHPDoc type for object collections (ArrayObject, etc.).
     *
     * Converts `ItemDto[]|\ArrayObject` to `\ArrayObject<int, ItemDto>`.
     *
     * @param array<string, mixed> $field
     *
     * @return string
     */
    public function buildGenericCollectionType(array $field): string
    {
        $collectionType = $field['collectionType'] ?? '\ArrayObject';
        $elementType = $field['singularType'] ?? 'mixed';

        // Include nullable in element type if singularNullable is set
        if (!empty($field['singularNullable']) && $elementType !== 'mixed') {
            $elementType .= '|null';
        }

        $keyType = $field['associative'] ? 'string' : 'int';

        return sprintf('%s<%s, %s>', $collectionType, $keyType, $elementType);
    }

    /**
     * Collect all inherited fields from parent DTOs recursively.
     *
     * @param array<string, mixed> $dto
     * @param array<string, array<string, mixed>> $allDtos
     *
     * @return array<string, array<string, mixed>>
     */
    protected function collectInheritedFields(array $dto, array $allDtos): array
    {
        $fields = [];

        // First, get grandparent fields if this DTO also extends something
        if (!empty($dto['extends'])) {
            $parentDtoName = $this->extractDtoName($dto['extends']);
            if ($parentDtoName && isset($allDtos[$parentDtoName])) {
                $fields = $this->collectInheritedFields($allDtos[$parentDtoName], $allDtos);
            }
        }

        // Then add this DTO's fields
        foreach ($dto['fields'] as $name => $field) {
            $fields[$name] = $field;
        }

        return $fields;
    }

    /**
     * Build the shaped array type for a single field.
     *
     * @param array<string, mixed> $field
     * @param array<string, array<string, mixed>> $allDtos
     *
     * @return string
     */
    protected function buildFieldShapeType(array $field, array $allDtos = []): string
    {
        // For collections, use array<keyType, elementType>
        if (!empty($field['collection']) || !empty($field['isArray'])) {
            $elementType = $field['singularType'] ?? 'mixed';
            $keyType = !empty($field['associative']) ? 'string' : 'int';

            // If element is a DTO, try to resolve its shape
            $dtoName = $this->extractDtoName($elementType);
            if ($dtoName && isset($allDtos[$dtoName])) {
                $nestedShape = $this->buildArrayShape($allDtos[$dtoName]['fields'], $allDtos);
                $elementType = $nestedShape;
            }

            $type = sprintf('array<%s, %s>', $keyType, $elementType);
        } elseif (!empty($field['dto'])) {
            // For nested DTOs, build nested shape if available
            $dtoName = $this->extractDtoName($field['type']);
            if ($dtoName && isset($allDtos[$dtoName])) {
                $type = $this->buildArrayShape($allDtos[$dtoName]['fields'], $allDtos);
            } else {
                $type = 'array<string, mixed>';
            }
        } else {
            // Simple type
            $type = $field['typeHint'] ?? $field['type'] ?? 'mixed';
        }

        // Add null if nullable
        if (!empty($field['nullable'])) {
            $type .= '|null';
        }

        return $type;
    }

    /**
     * Extract the DTO name from a fully qualified class name.
     *
     * @param string $type
     *
     * @return string|null
     */
    protected function extractDtoName(string $type): ?string
    {
        // Remove leading backslash and namespace, extract class name without Dto suffix
        $className = ltrim($type, '\\');
        $parts = explode('\\', $className);
        $shortName = end($parts);

        // Remove Dto suffix if present
        if (str_ends_with($shortName, $this->suffix)) {
            return substr($shortName, 0, -strlen($this->suffix));
        }

        return $shortName;
    }
}
