<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use InvalidArgumentException;
use PhpCollective\Dto\Utility\Inflector;

/**
 * Completes and enriches field definitions with computed values.
 */
class FieldCompletor
{
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
     * @var string
     */
    protected string $defaultCollectionType;

    /**
     * @var string
     */
    protected string $suffix;

    /**
     * @var bool
     */
    protected bool $scalarAndReturnTypes;

    /**
     * @param \PhpCollective\Dto\Generator\TypeValidator $typeValidator
     * @param \PhpCollective\Dto\Generator\TypeResolver $typeResolver
     * @param \PhpCollective\Dto\Generator\ArrayShapeBuilder $arrayShapeBuilder
     * @param array<string, mixed> $config
     */
    public function __construct(
        TypeValidator $typeValidator,
        TypeResolver $typeResolver,
        ArrayShapeBuilder $arrayShapeBuilder,
        array $config,
    ) {
        $this->typeValidator = $typeValidator;
        $this->typeResolver = $typeResolver;
        $this->arrayShapeBuilder = $arrayShapeBuilder;
        $this->defaultCollectionType = $config['defaultCollectionType'] ?? '\ArrayObject';
        $this->suffix = $config['suffix'] ?? 'Dto';
        $this->scalarAndReturnTypes = $config['scalarAndReturnTypes'] ?? true;
    }

    /**
     * Complete field definitions with defaults and computed values.
     *
     * @param array<string, mixed> $dto
     * @param string $namespace
     *
     * @return array<string, mixed>
     */
    public function complete(array $dto, string $namespace): array
    {
        $dtoName = $dto['name'];
        $fields = $dto['fields'];

        $fields = $this->addFieldDefaults($fields);
        $fields = $this->resolveFieldTypes($fields, $dtoName, $namespace);

        $dto['fields'] = $fields;

        return $dto;
    }

    /**
     * Complete field metadata (type hints, return types, etc.).
     *
     * @param array<string, mixed> $dto
     * @param string $namespace
     *
     * @return array<string, mixed>
     */
    public function completeMeta(array $dto, string $namespace): array
    {
        $fields = $dto['fields'];

        foreach ($fields as $key => $field) {
            $fields[$key] = $this->completeFieldTypeHints($field, $namespace);
            $fields[$key] = $this->completeCollectionTypeHints($fields[$key]);
            $fields[$key] = $this->completeArrayTypeHints($fields[$key]);
            $fields[$key] = $this->completeNullableTypeHints($fields[$key]);
            $fields[$key] = $this->completeSingularTypeHints($fields[$key]);
        }

        $dto['fields'] = $fields;
        $dto += [
            'deprecated' => null,
        ];

        return $dto;
    }

    /**
     * Add default values to all fields.
     *
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    protected function addFieldDefaults(array $fields): array
    {
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
                'transformFrom' => null,
                'transformTo' => null,
                'minLength' => null,
                'maxLength' => null,
                'min' => null,
                'max' => null,
                'pattern' => null,
                'lazy' => false,
            ];

            if ($data['required']) {
                $data['nullable'] = false;
            }

            $fields[$field] = $data;
        }

        return $fields;
    }

    /**
     * Resolve and validate field types.
     *
     * @param array<string, mixed> $fields
     * @param string $dtoName
     * @param string $namespace
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function resolveFieldTypes(array $fields, string $dtoName, string $namespace): array
    {
        foreach ($fields as $key => $field) {
            if ($this->typeValidator->isValidSimpleType($field['type'], $this->typeValidator->getSimpleTypeAdditionsForDocBlock())) {
                continue;
            }

            if ($this->typeValidator->isValidDto($field['type'])) {
                $fields[$key]['dto'] = $field['type'];

                continue;
            }

            if ($this->isCollection($field)) {
                $fields[$key] = $this->resolveCollectionField($fields[$key], $dtoName, $namespace, $fields);

                continue;
            }

            if ($this->typeValidator->isValidArray($field['type'])) {
                $fields[$key]['isArray'] = true;
                if (preg_match('#^([A-Z][a-zA-Z/]+)\[\]$#', $field['type'], $matches)) {
                    $fields[$key]['type'] = $this->typeResolver->dtoTypeToClass($matches[1], $namespace, $this->suffix) . '[]';
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
                "Invalid type `%s` for field `%s` in `%s` DTO.\n"
                . 'Hint: Valid types include: scalar types (int, string, bool, float), '
                . 'DTO references (OtherDto), arrays (string[], OtherDto[]), '
                . 'or fully qualified class names (\\App\\MyClass).',
                $field['type'],
                $key,
                $dtoName,
            ));
        }

        return $fields;
    }

    /**
     * Check if a field is a collection.
     *
     * @param array<string, mixed> $field
     *
     * @return bool
     */
    public function isCollection(array $field): bool
    {
        return $field['collection'] || $field['collectionType'] || $field['associative'];
    }

    /**
     * Resolve collection field configuration.
     *
     * @param array<string, mixed> $field
     * @param string $dtoName
     * @param string $namespace
     * @param array<string, mixed> $allFields
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function resolveCollectionField(array $field, string $dtoName, string $namespace, array $allFields): array
    {
        $field['collection'] = true;
        $field['collectionType'] = $this->typeResolver->collectionType($field, $this->defaultCollectionType);
        $field['nullable'] = false;

        $field = $this->completeCollectionSingular($field, $dtoName, $namespace, $allFields);
        $field['singularNullable'] = substr($field['type'], 0, 1) === '?';

        if (!empty($field['singular'])) {
            $singular = $field['singular'];
            if (!empty($allFields[$singular])) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid singular name `%s` for collection field `%s` in `%s` DTO.\n"
                    . 'Hint: The singular name conflicts with existing field `%s`. Use a different singular name.',
                    $singular,
                    $field['name'],
                    $dtoName,
                    $singular,
                ));
            }
        }

        if (preg_match('#^([A-Z][a-zA-Z/]+)\[\]$#', $field['type'], $matches)) {
            $field['type'] = $this->typeResolver->dtoTypeToClass($matches[1], $namespace, $this->suffix) . '[]';
        }

        if ($field['singularNullable']) {
            $field['type'] = '(' . $field['singularType'] . '|null)[]';
        }

        return $field;
    }

    /**
     * Complete collection singular configuration.
     *
     * @param array<string, mixed> $data
     * @param string $dtoName
     * @param string $namespace
     * @param array<string, mixed> $fields
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function completeCollectionSingular(array $data, string $dtoName, string $namespace, array $fields): array
    {
        $fieldName = $data['name'];
        if (!$data['collection'] && empty($data['collectionType'])) {
            return $data;
        }

        $data['singularType'] = $this->typeResolver->singularType($data['type']);
        if ($data['singularType'] && $this->typeValidator->isValidDto($data['singularType'])) {
            $data['singularType'] = $this->typeResolver->dtoTypeToClass($data['singularType'], $namespace, $this->suffix);
            $data['singularClass'] = $data['singularType'];
        }

        if (!empty($data['singular'])) {
            return $data;
        }

        $singular = Inflector::singularize($fieldName);
        if ($singular === $fieldName) {
            throw new InvalidArgumentException(sprintf(
                "Cannot auto-singularize field name `%s` in `%s` DTO.\n"
                . 'Hint: The field name `%s` has no singular form. Add an explicit `singular` attribute (e.g., singular="%sItem").',
                $fieldName,
                $dtoName,
                $fieldName,
                $fieldName,
            ));
        }

        // Collision detection
        if (!empty($fields[$singular])) {
            throw new InvalidArgumentException(sprintf(
                "Auto-generated singular `%s` for collection field `%s` in `%s` DTO collides with existing field.\n"
                . 'Hint: Add an explicit `singular` attribute with a unique name to avoid this collision.',
                $singular,
                $fieldName,
                $dtoName,
            ));
        }

        $data['singular'] = $singular;

        return $data;
    }

    /**
     * Complete field type hints.
     *
     * @param array<string, mixed> $field
     * @param string $namespace
     *
     * @return array<string, mixed>
     */
    protected function completeFieldTypeHints(array $field, string $namespace): array
    {
        if ($field['dto']) {
            $className = $this->typeResolver->dtoTypeToClass($field['type'], $namespace, $this->suffix);
            $field['type'] = $className;
            $field['typeHint'] = $className;
        } else {
            $field['typeHint'] = $field['type'];
        }

        $field['typeHint'] = $this->typeResolver->typehint($field['typeHint']);

        return $field;
    }

    /**
     * Complete collection type hints.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    protected function completeCollectionTypeHints(array $field): array
    {
        if (!$field['collection']) {
            return $field;
        }

        if ($field['collectionType'] === 'array') {
            $field['typeHint'] = 'array';
            $field['docBlockType'] = $this->arrayShapeBuilder->buildGenericArrayType($field);
        } else {
            $field['typeHint'] = $field['collectionType'];
            $field['type'] .= '|' . $field['typeHint'];
            $field['docBlockType'] = $this->arrayShapeBuilder->buildGenericCollectionType($field);
        }

        return $field;
    }

    /**
     * Complete array type hints.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    protected function completeArrayTypeHints(array $field): array
    {
        if (!$field['isArray']) {
            return $field;
        }

        if ($field['type'] !== 'array') {
            $field['typeHint'] = 'array';
            $field['docBlockType'] = $this->arrayShapeBuilder->buildGenericArrayType($field);
        }

        return $field;
    }

    /**
     * Complete nullable type hints.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    protected function completeNullableTypeHints(array $field): array
    {
        if ($field['typeHint'] && $this->scalarAndReturnTypes) {
            $field['returnTypeHint'] = $field['typeHint'];

            if ($field['nullable']) {
                if ($this->typeResolver->isUnionType($field['typeHint'])) {
                    $field['nullableReturnTypeHint'] = $field['typeHint'] . '|null';
                } else {
                    $field['nullableReturnTypeHint'] = '?' . $field['typeHint'];
                }
            }
        }

        if ($field['typeHint'] && $this->scalarAndReturnTypes && $field['nullable']) {
            if ($this->typeResolver->isUnionType($field['typeHint'])) {
                $field['nullableTypeHint'] = $field['typeHint'] . '|null';
            } else {
                $field['nullableTypeHint'] = '?' . $field['typeHint'];
            }
        }

        return $field;
    }

    /**
     * Complete singular type hints for collections.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    protected function completeSingularTypeHints(array $field): array
    {
        if (!$field['collection']) {
            return $field;
        }

        $field += [
            'singularTypeHint' => null,
            'singularNullable' => false,
            'singularReturnTypeHint' => null,
            'singularNullableReturnTypeHint' => null,
        ];

        if ($field['singularType']) {
            $field['singularTypeHint'] = $this->typeResolver->typehint($field['singularType']);
        }

        if ($field['singularTypeHint'] && $this->scalarAndReturnTypes) {
            $field['singularReturnTypeHint'] = $field['singularTypeHint'];

            if ($field['singularNullable']) {
                if ($this->typeResolver->isUnionType($field['singularTypeHint'])) {
                    $field['singularNullableReturnTypeHint'] = $field['singularTypeHint'] . '|null';
                } else {
                    $field['singularNullableReturnTypeHint'] = '?' . $field['singularTypeHint'];
                }
            }
        }

        $field['keyType'] = !empty($field['associative']) ? 'string' : 'int';

        return $field;
    }
}
