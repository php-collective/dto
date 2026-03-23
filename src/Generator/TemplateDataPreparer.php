<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Prepares DTO data for template rendering by pre-computing derived values.
 *
 * Moves complex logic from Twig templates into PHP for better maintainability,
 * testability, and readability.
 */
class TemplateDataPreparer
{
    /**
     * Prepare DTO data for template rendering.
     *
     * @param array<string, mixed> $dto
     *
     * @return array<string, mixed>
     */
    public function prepare(array $dto): array
    {
        $dto = $this->computeRequiredImports($dto);
        $dto = $this->computeFieldRenderData($dto);

        return $dto;
    }

    /**
     * Compute which imports/exceptions are needed.
     *
     * @param array<string, mixed> $dto
     *
     * @return array<string, mixed>
     */
    protected function computeRequiredImports(array $dto): array
    {
        $needsInvalidArgumentException = false;
        $needsRuntimeException = false;

        foreach ($dto[FieldKey::FIELDS] as $field) {
            if (!empty($field[FieldKey::REQUIRED])) {
                $needsInvalidArgumentException = true;
            }
            if (!empty($field[FieldKey::NULLABLE])) {
                $needsRuntimeException = true;
            }
            if (
                !empty($field[FieldKey::ASSOCIATIVE])
                && !empty($field[FieldKey::SINGULAR])
                && empty($field[FieldKey::SINGULAR_NULLABLE])
            ) {
                $needsRuntimeException = true;
            }
        }

        $dto['needsInvalidArgumentException'] = $needsInvalidArgumentException;
        $dto['needsRuntimeException'] = $needsRuntimeException;

        return $dto;
    }

    /**
     * Pre-compute rendering data for each field.
     *
     * @param array<string, mixed> $dto
     *
     * @return array<string, mixed>
     */
    protected function computeFieldRenderData(array $dto): array
    {
        $immutable = !empty($dto[FieldKey::IMMUTABLE]);
        $readonlyProperties = !empty($dto[FieldKey::READONLY_PROPERTIES]);

        foreach ($dto[FieldKey::FIELDS] as $name => $field) {
            // Method generation flags
            $field['useWithMethod'] = $immutable;
            $field['needsOrFailMethod'] = !empty($field[FieldKey::NULLABLE]);
            $field['needsHasMethod'] = !empty($field[FieldKey::NULLABLE])
                || ($field[FieldKey::DEFAULT_VALUE] ?? null) === null;

            // Collection method flags
            $field['useImmutableCollectionMethods'] = $immutable && !empty($field[FieldKey::COLLECTION]);
            $field['useMutableCollectionMethods'] = !$immutable
                && !empty($field[FieldKey::COLLECTION_TYPE])
                && !empty($field[FieldKey::SINGULAR]);

            // Property declaration
            $field['propertyVisibility'] = $readonlyProperties ? 'public readonly' : 'protected';
            $field['propertyTypeHint'] = $this->computePropertyTypeHint($field);
            $field['propertyDefault'] = $this->computePropertyDefault($field, $readonlyProperties);

            $dto[FieldKey::FIELDS][$name] = $field;
        }

        return $dto;
    }

    /**
     * Compute the type hint for a property declaration.
     *
     * @param array<string, mixed> $field
     *
     * @return string|null
     */
    protected function computePropertyTypeHint(array $field): ?string
    {
        if (empty($field['returnTypeHint'])) {
            return null;
        }

        // Collections with non-array type need nullable type hint
        if (!empty($field[FieldKey::COLLECTION]) && ($field[FieldKey::COLLECTION_TYPE] ?? '') !== 'array') {
            return '?' . $field['typeHint'];
        }

        return $field['nullableTypeHint'] ?? $field['typeHint'];
    }

    /**
     * Compute the default value for a property.
     *
     * @param array<string, mixed> $field
     * @param bool $readonlyProperties
     *
     * @return string|null
     */
    protected function computePropertyDefault(array $field, bool $readonlyProperties): ?string
    {
        // Readonly properties cannot have default values in the declaration
        if ($readonlyProperties) {
            return null;
        }

        if (!empty($field[FieldKey::NULLABLE])) {
            return 'null';
        }

        $defaultValue = $field[FieldKey::DEFAULT_VALUE] ?? null;
        if ($defaultValue !== null) {
            return $this->phpExport($defaultValue);
        }

        // Non-array collections default to null
        if (!empty($field[FieldKey::COLLECTION]) && ($field[FieldKey::COLLECTION_TYPE] ?? '') !== 'array') {
            return 'null';
        }

        return null;
    }

    /**
     * Export a PHP value to its code representation.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function phpExport(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_string($value)) {
            return "'" . addcslashes($value, "'\\") . "'";
        }
        if (is_array($value)) {
            return var_export($value, true);
        }

        return (string)$value;
    }
}
