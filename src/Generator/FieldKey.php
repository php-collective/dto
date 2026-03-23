<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Constants for field metadata keys.
 *
 * Using these constants instead of magic strings improves:
 * - IDE autocompletion and refactoring support
 * - Static analysis and typo detection
 * - Code maintainability
 */
final class FieldKey
{
    // DTO-level attributes
    /**
     * @var string
     */
    public const FIELDS = 'fields';

    // Core field attributes
    /**
     * @var string
     */
    public const NAME = 'name';

    /**
     * @var string
     */
    public const TYPE = 'type';

    /**
     * @var string
     */
    public const REQUIRED = 'required';

    /**
     * @var string
     */
    public const DEFAULT_VALUE = 'defaultValue';

    /**
     * @var string
     */
    public const NULLABLE = 'nullable';

    /**
     * @var string
     */
    public const DEPRECATED = 'deprecated';

    // Type information
    /**
     * @var string
     */
    public const TYPE_HINT = 'typeHint';

    /**
     * @var string
     */
    public const RETURN_TYPE_HINT = 'returnTypeHint';

    /**
     * @var string
     */
    public const NULLABLE_TYPE_HINT = 'nullableTypeHint';

    /**
     * @var string
     */
    public const NULLABLE_RETURN_TYPE_HINT = 'nullableReturnTypeHint';

    /**
     * @var string
     */
    public const DOC_BLOCK_TYPE = 'docBlockType';

    /**
     * @var string
     */
    public const IS_CLASS = 'isClass';

    /**
     * @var string
     */
    public const IS_ARRAY = 'isArray';

    /**
     * @var string
     */
    public const DTO = 'dto';

    /**
     * @var string
     */
    public const ENUM = 'enum';

    // Collection attributes
    /**
     * @var string
     */
    public const COLLECTION = 'collection';

    /**
     * @var string
     */
    public const COLLECTION_TYPE = 'collectionType';

    /**
     * @var string
     */
    public const SINGULAR = 'singular';

    /**
     * @var string
     */
    public const SINGULAR_TYPE = 'singularType';

    /**
     * @var string
     */
    public const SINGULAR_TYPE_HINT = 'singularTypeHint';

    /**
     * @var string
     */
    public const SINGULAR_NULLABLE = 'singularNullable';

    /**
     * @var string
     */
    public const SINGULAR_CLASS = 'singularClass';

    /**
     * @var string
     */
    public const SINGULAR_RETURN_TYPE_HINT = 'singularReturnTypeHint';

    /**
     * @var string
     */
    public const SINGULAR_NULLABLE_RETURN_TYPE_HINT = 'singularNullableReturnTypeHint';

    /**
     * @var string
     */
    public const ASSOCIATIVE = 'associative';

    /**
     * @var string
     */
    public const KEY = 'key';

    /**
     * @var string
     */
    public const KEY_TYPE = 'keyType';

    // Serialization and transformation
    /**
     * @var string
     */
    public const SERIALIZE = 'serialize';

    /**
     * @var string
     */
    public const FACTORY = 'factory';

    /**
     * @var string
     */
    public const MAP_FROM = 'mapFrom';

    /**
     * @var string
     */
    public const MAP_TO = 'mapTo';

    /**
     * @var string
     */
    public const TRANSFORM_FROM = 'transformFrom';

    /**
     * @var string
     */
    public const TRANSFORM_TO = 'transformTo';

    // Validation rules
    /**
     * @var string
     */
    public const MIN_LENGTH = 'minLength';

    /**
     * @var string
     */
    public const MAX_LENGTH = 'maxLength';

    /**
     * @var string
     */
    public const MIN = 'min';

    /**
     * @var string
     */
    public const MAX = 'max';

    /**
     * @var string
     */
    public const PATTERN = 'pattern';

    // Behavior flags
    /**
     * @var string
     */
    public const LAZY = 'lazy';

    /**
     * Keys that are included in runtime metadata.
     *
     * @return array<string>
     */
    public static function metadataKeys(): array
    {
        return [
            self::NAME,
            self::TYPE,
            self::IS_CLASS,
            self::ENUM,
            self::SERIALIZE,
            self::FACTORY,
            self::REQUIRED,
            self::DEFAULT_VALUE,
            self::DTO,
            self::COLLECTION_TYPE,
            self::SINGULAR_TYPE,
            self::SINGULAR_TYPE_HINT,
            self::SINGULAR_NULLABLE,
            self::ASSOCIATIVE,
            self::KEY,
            self::MAP_FROM,
            self::MAP_TO,
            self::TRANSFORM_FROM,
            self::TRANSFORM_TO,
            self::MIN_LENGTH,
            self::MAX_LENGTH,
            self::MIN,
            self::MAX,
            self::PATTERN,
            self::LAZY,
        ];
    }

    /**
     * Default values for field attributes.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            self::REQUIRED => false,
            self::DEFAULT_VALUE => null,
            self::NULLABLE => true,
            self::RETURN_TYPE_HINT => null,
            self::NULLABLE_TYPE_HINT => null,
            self::IS_ARRAY => false,
            self::DTO => null,
            self::COLLECTION => false,
            self::COLLECTION_TYPE => null,
            self::ASSOCIATIVE => false,
            self::KEY => null,
            self::DEPRECATED => null,
            self::SERIALIZE => null,
            self::FACTORY => null,
            self::MAP_FROM => null,
            self::MAP_TO => null,
            self::TRANSFORM_FROM => null,
            self::TRANSFORM_TO => null,
            self::MIN_LENGTH => null,
            self::MAX_LENGTH => null,
            self::MIN => null,
            self::MAX => null,
            self::PATTERN => null,
            self::LAZY => false,
        ];
    }
}
