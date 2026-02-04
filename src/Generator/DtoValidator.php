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
        if (empty($dto['name'])) {
            throw new InvalidArgumentException(
                "DTO name missing, but required.\n"
                . 'Hint: Each DTO definition must have a "name" attribute.',
            );
        }

        $dtoName = $dto['name'];
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
        $dtoName = $dto['name'];
        $fields = $dto['fields'];

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
        if (empty($field['name'])) {
            throw new InvalidArgumentException(sprintf(
                "Field attribute `name` missing for field `%s` in `%s` DTO.\n"
                . 'Hint: Each field must have a "name" attribute.',
                $fieldKey,
                $dtoName,
            ));
        }

        if (!$this->typeValidator->isValidName($field['name'])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid field name `%s` in `%s` DTO.\n"
                . 'Hint: Field names must be alphanumeric starting with a letter (e.g., "userName", "itemCount").',
                $field['name'],
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
        if (empty($field['type'])) {
            throw new InvalidArgumentException(sprintf(
                "Field attribute `type` missing for field `%s` in `%s` DTO.\n"
                . 'Hint: Each field must have a "type" attribute (e.g., "string", "int", "ItemDto[]").',
                $fieldKey,
                $dtoName,
            ));
        }

        if (!$this->typeValidator->isValidType($field['type'])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid type `%s` for field `%s` in `%s` DTO.\n"
                . 'Hint: Valid types include: scalar types (int, string, bool, float), '
                . 'DTO references (OtherDto), arrays (string[], OtherDto[]), '
                . 'or fully qualified class names (\\App\\MyClass).',
                $field['type'],
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
        if (empty($field['collection'])) {
            return;
        }

        if (!$this->typeValidator->isValidArray($field['type']) || !$this->typeValidator->isValidCollection($field['type'])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid collection type `%s` for field `%s` in `%s` DTO.\n"
                . 'Hint: Collection types must use array notation (e.g., "string[]", "ItemDto[]").',
                $field['type'],
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
        if (empty($field['singular'])) {
            return;
        }

        $expected = Inflector::variable(Inflector::underscore($field['singular']));
        if ($field['singular'] !== $expected) {
            throw new InvalidArgumentException(sprintf(
                "Invalid singular name `%s` for field `%s` in `%s` DTO.\n"
                . 'Hint: Expected `%s` (camelCase format).',
                $field['singular'],
                $fieldKey,
                $dtoName,
                $expected,
            ));
        }

        if (isset($field['collection']) && $field['collection'] === false) {
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
        $dtoName = $dto['name'];
        $fields = $dto['fields'];
        $methodNames = [];

        foreach ($fields as $array) {
            $fieldName = $array['name'];
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

        $dtoName = $existing['name'] ?? 'unknown';

        foreach ($existing as $field => $info) {
            if (!isset($new[$field])) {
                continue;
            }
            if (!isset($info['type'])) {
                continue;
            }
            if (!isset($new[$field]['type'])) {
                continue;
            }

            if ($info['type'] !== $new[$field]['type']) {
                throw new RuntimeException(sprintf(
                    "Type mismatch for field `%s` in `%s` DTO during merge.\n"
                    . "Existing type: `%s`, new type: `%s`.\n"
                    . 'Hint: Field types must be consistent across all configuration files.',
                    $field,
                    $dtoName,
                    $info['type'],
                    $new[$field]['type'],
                ));
            }
        }
    }
}
