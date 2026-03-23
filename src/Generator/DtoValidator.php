<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use InvalidArgumentException;
use PhpCollective\Dto\Utility\Inflector;
use RuntimeException;

/**
 * Validates DTO configuration.
 */
class DtoValidator
{
    /**
     * @var \PhpCollective\Dto\Generator\TypeValidator
     */
    protected TypeValidator $typeValidator;

    /**
     * @param \PhpCollective\Dto\Generator\TypeValidator $typeValidator
     */
    public function __construct(TypeValidator $typeValidator)
    {
        $this->typeValidator = $typeValidator;
    }

    /**
     * Validate a DTO definition.
     *
     * @param array<string, mixed> $dto
     *
     * @return void
     */
    public function validate(array $dto): void
    {
        $this->validateDtoName($dto);
        $this->validateFields($dto);
        $this->validateMethodNameCollisions($dto);
    }

    /**
     * Validate DTO name.
     *
     * @param array<string, mixed> $dto
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateDtoName(array $dto): void
    {
        if (empty($dto[FieldKey::NAME])) {
            throw new InvalidArgumentException(
                "DTO name missing, but required.\n"
                . 'Hint: Each DTO definition must have a "name" attribute.',
            );
        }

        $dtoName = $dto[FieldKey::NAME];
        if (!$this->typeValidator->isValidDto($dtoName)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid DTO name `%s`.\n"
                . 'Hint: DTO names must be PascalCase starting with uppercase letter (e.g., "UserProfile", "OrderItem").',
                $dtoName,
            ));
        }
    }

    /**
     * Validate all fields in a DTO.
     *
     * @param array<string, mixed> $dto
     *
     * @return void
     */
    protected function validateFields(array $dto): void
    {
        $dtoName = $dto[FieldKey::NAME];
        $fields = $dto[FieldKey::FIELDS];

        foreach ($fields as $name => $array) {
            $this->validateFieldName($array, $name, $dtoName);
            $this->validateFieldType($array, $name, $dtoName);
            $this->validateFieldAttributes($array, $name, $dtoName);
            $this->validateFieldCollection($array, $name, $dtoName);
            $this->validateFieldSingular($array, $name, $dtoName);
        }
    }

    /**
     * Validate field name attribute.
     *
     * @param array<string, mixed> $field
     * @param string $fieldKey
     * @param string $dtoName
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateFieldName(array $field, string $fieldKey, string $dtoName): void
    {
        if (empty($field[FieldKey::NAME])) {
            throw new InvalidArgumentException(sprintf(
                "Field attribute `name` missing for field `%s` in `%s` DTO.\n"
                . 'Hint: Each field must have a "name" attribute.',
                $fieldKey,
                $dtoName,
            ));
        }

        if (!$this->typeValidator->isValidName($field[FieldKey::NAME])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid field name `%s` in `%s` DTO.\n"
                . 'Hint: Field names must be alphanumeric starting with a letter (e.g., "userName", "itemCount").',
                $field[FieldKey::NAME],
                $dtoName,
            ));
        }
    }

    /**
     * Validate field type attribute.
     *
     * @param array<string, mixed> $field
     * @param string $fieldKey
     * @param string $dtoName
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateFieldType(array $field, string $fieldKey, string $dtoName): void
    {
        if (empty($field[FieldKey::TYPE])) {
            throw new InvalidArgumentException(sprintf(
                "Field attribute `type` missing for field `%s` in `%s` DTO.\n"
                . 'Hint: Each field must have a "type" attribute (e.g., "string", "int", "ItemDto[]").',
                $fieldKey,
                $dtoName,
            ));
        }

        if (!$this->typeValidator->isValidType($field[FieldKey::TYPE])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type `%s` for field `%s` in `%s` DTO.\n"
                . 'Hint: Valid types include: scalar types (int, string, bool, float), '
                . 'DTO references (OtherDto), arrays (string[], OtherDto[]), '
                . 'or fully qualified class names (\\App\\MyClass).',
                $field[FieldKey::TYPE],
                $fieldKey,
                $dtoName,
            ));
        }
    }

    /**
     * Validate field attributes are in camelCase format.
     *
     * @param array<string, mixed> $field
     * @param string $fieldKey
     * @param string $dtoName
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateFieldAttributes(array $field, string $fieldKey, string $dtoName): void
    {
        foreach ($field as $key => $value) {
            $expected = Inflector::variable(Inflector::underscore($key));
            if ($key !== $expected) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid field attribute `%s` for field `%s` in `%s` DTO.\n"
                    . 'Hint: Expected `%s` (camelCase format).',
                    $key,
                    $fieldKey,
                    $dtoName,
                    $expected,
                ));
            }
        }
    }

    /**
     * Validate field collection configuration.
     *
     * @param array<string, mixed> $field
     * @param string $fieldKey
     * @param string $dtoName
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateFieldCollection(array $field, string $fieldKey, string $dtoName): void
    {
        if (empty($field[FieldKey::COLLECTION])) {
            return;
        }

        if (!$this->typeValidator->isValidArray($field[FieldKey::TYPE]) || !$this->typeValidator->isValidCollection($field[FieldKey::TYPE])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid collection type `%s` for field `%s` in `%s` DTO.\n"
                . 'Hint: Collection types must use array notation (e.g., "string[]", "ItemDto[]").',
                $field[FieldKey::TYPE],
                $fieldKey,
                $dtoName,
            ));
        }
    }

    /**
     * Validate field singular configuration.
     *
     * @param array<string, mixed> $field
     * @param string $fieldKey
     * @param string $dtoName
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateFieldSingular(array $field, string $fieldKey, string $dtoName): void
    {
        if (empty($field[FieldKey::SINGULAR])) {
            return;
        }

        $expected = Inflector::variable(Inflector::underscore($field[FieldKey::SINGULAR]));
        if ($field[FieldKey::SINGULAR] !== $expected) {
            throw new InvalidArgumentException(sprintf(
                "Invalid singular name `%s` for field `%s` in `%s` DTO.\n"
                . 'Hint: Expected `%s` (camelCase format).',
                $field[FieldKey::SINGULAR],
                $fieldKey,
                $dtoName,
                $expected,
            ));
        }

        if (isset($field[FieldKey::COLLECTION]) && $field[FieldKey::COLLECTION] === false) {
            throw new InvalidArgumentException(sprintf(
                "Invalid `singular` attribute for non-collection field `%s` in `%s` DTO.\n"
                . 'Hint: The "singular" attribute is only valid for collection fields.',
                $fieldKey,
                $dtoName,
            ));
        }
    }

    /**
     * Check for method name collisions between fields.
     *
     * @param array<string, mixed> $dto
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    protected function validateMethodNameCollisions(array $dto): void
    {
        $dtoName = $dto[FieldKey::NAME];
        $fields = $dto[FieldKey::FIELDS];
        $methodNames = [];

        foreach ($fields as $array) {
            $fieldName = $array[FieldKey::NAME];
            $methodName = Inflector::camelize($fieldName);

            if (isset($methodNames[$methodName])) {
                throw new InvalidArgumentException(sprintf(
                    "Field name collision in `%s` DTO: fields `%s` and `%s` would generate identical method names.\n"
                    . 'Hint: Both fields would generate methods like `get%s()`, `set%s()`, etc. Use only one of these field names.',
                    $dtoName,
                    $methodNames[$methodName],
                    $fieldName,
                    $methodName,
                    $methodName,
                ));
            }
            $methodNames[$methodName] = $fieldName;
        }
    }

    /**
     * Validate merge compatibility between two DTO definitions.
     *
     * Ensures that when the same field is defined in multiple files, the types are consistent.
     *
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed>|null $new
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function validateMerge(?array $existing, ?array $new): void
    {
        if (!$existing || !$new) {
            return;
        }

        $dtoName = $existing[FieldKey::NAME] ?? 'unknown';
        $existingFields = $existing[FieldKey::FIELDS] ?? [];
        $newFields = $new[FieldKey::FIELDS] ?? [];

        foreach ($existingFields as $fieldName => $fieldInfo) {
            if (!isset($newFields[$fieldName])) {
                continue;
            }
            if (!isset($fieldInfo[FieldKey::TYPE])) {
                continue;
            }
            if (!isset($newFields[$fieldName][FieldKey::TYPE])) {
                continue;
            }

            if ($fieldInfo[FieldKey::TYPE] !== $newFields[$fieldName][FieldKey::TYPE]) {
                throw new RuntimeException(sprintf(
                    "Type mismatch for field `%s` in `%s` DTO during merge.\n"
                    . "Existing type: `%s`, new type: `%s`.\n"
                    . 'Hint: Field types must be consistent across all configuration files.',
                    $fieldName,
                    $dtoName,
                    $fieldInfo[FieldKey::TYPE],
                    $newFields[$fieldName][FieldKey::TYPE],
                ));
            }
        }
    }
}
