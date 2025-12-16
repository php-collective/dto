<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use JsonSerializable;
use PhpCollective\Dto\Dto\FromArrayToArrayInterface;
use ReflectionEnum;
use ReflectionException;

/**
 * Resolves and transforms types for DTO generation.
 */
class TypeResolver
{
    /**
     * @var \PhpCollective\Dto\Generator\TypeValidator
     */
    protected TypeValidator $validator;

    /**
     * @var array<string>
     */
    protected array $simpleTypeAdditionsForDocBlock = [
        'resource',
        'mixed',
    ];

    /**
     * @var bool
     */
    protected bool $scalarAndReturnTypes;

    /**
     * @param \PhpCollective\Dto\Generator\TypeValidator $validator
     * @param bool $scalarAndReturnTypes
     */
    public function __construct(TypeValidator $validator, bool $scalarAndReturnTypes = true)
    {
        $this->validator = $validator;
        $this->scalarAndReturnTypes = $scalarAndReturnTypes;
    }

    /**
     * Get the type hint for a given type.
     *
     * @param string $type
     *
     * @return string|null
     */
    public function typehint(string $type): ?string
    {
        // Handle simple type unions
        if ($this->validator->isValidSimpleType($type)) {
            $types = explode('|', $type);
            if (count($types) > 1) {
                // PHP doesn't support array notation in union types
                foreach ($types as $t) {
                    if (str_ends_with($t, '[]')) {
                        return 'array';
                    }
                }

                return $type;
            }
        }
        if (in_array($type, $this->simpleTypeAdditionsForDocBlock, true)) {
            return null;
        }
        if (!$this->scalarAndReturnTypes && in_array($type, $this->validator->getSimpleTypeWhitelist(), true)) {
            return null;
        }

        return $type;
    }

    /**
     * Get the singular type from an array type.
     *
     * @param string $type
     *
     * @return string|null
     */
    public function singularType(string $type): ?string
    {
        if (substr($type, -2) !== '[]') {
            return null;
        }

        $type = substr($type, 0, -2);
        if (substr($type, 0, 1) === '?') {
            $type = substr($type, 1);
        }

        if (
            !$this->validator->isValidSimpleType($type) &&
            !$this->validator->isValidDto($type) &&
            !$this->validator->isValidInterfaceOrClass($type)
        ) {
            return null;
        }

        return $type;
    }

    /**
     * Get the collection type for a field.
     *
     * @param array<string, mixed> $field
     * @param string $defaultCollectionType
     *
     * @return string
     */
    public function collectionType(array $field, string $defaultCollectionType): string
    {
        if ($field['collectionType']) {
            return $field['collectionType'];
        }
        if ($field['collection']) {
            return $defaultCollectionType;
        }

        return 'array';
    }

    /**
     * Check if a type is a union type.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isUnionType(string $type): bool
    {
        return str_contains($type, '|');
    }

    /**
     * Get the enum backing type for a class.
     *
     * @param class-string<\BackedEnum|\UnitEnum> $type
     *
     * @return string|null 'unit' for unit enums, backing type for backed enums, null if not an enum
     */
    public function enumType(string $type): ?string
    {
        try {
            $reflectionEnum = new ReflectionEnum($type);
        } catch (ReflectionException $e) {
            return null;
        }

        if (!$reflectionEnum->isBacked()) {
            return 'unit';
        }

        return (string)$reflectionEnum->getBackingType();
    }

    /**
     * Detect the serialize method to use for a class type.
     *
     * @param array<string, mixed> $config
     *
     * @return string|null
     */
    public function detectSerialize(array $config): ?string
    {
        $serializable = is_subclass_of($config['type'], FromArrayToArrayInterface::class);
        if ($serializable) {
            return 'FromArrayToArray';
        }

        $jsonSafeToString = is_subclass_of($config['type'], JsonSerializable::class);
        if ($jsonSafeToString) {
            return null;
        }

        if (method_exists($config['type'], 'toArray')) {
            return 'array';
        }

        return null;
    }

    /**
     * Convert a DTO type name to a fully qualified class name.
     *
     * @param string $singularType
     * @param string $namespace
     * @param string $suffix
     *
     * @return string
     */
    public function dtoTypeToClass(string $singularType, string $namespace, string $suffix): string
    {
        $className = str_replace('/', '\\', $singularType) . $suffix;

        return '\\' . $namespace . '\\Dto\\' . $className;
    }
}
