<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Parser;

/**
 * Configuration for schema parsers.
 */
class Config
{
    /**
     * @var array<string>
     */
    protected static array $keyFields = [
        'slug',
        'login',
        'name',
        'id',
    ];

    /**
     * Returns available parser type labels.
     *
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            DataParser::NAME => 'From JSON Data Example',
            SchemaParser::NAME => 'From JSON Schema File',
        ];
    }

    /**
     * Returns available parser types.
     *
     * @return array<string, class-string<\PhpCollective\Dto\Importer\Parser\ParserInterface>>
     */
    public static function types(): array
    {
        return [
            DataParser::NAME => DataParser::class,
            SchemaParser::NAME => SchemaParser::class,
        ];
    }

    /**
     * Returns fields that can be used as associative array keys.
     *
     * @return array<string>
     */
    public static function keyFields(): array
    {
        return static::$keyFields;
    }

    /**
     * Sets custom key fields.
     *
     * @param array<string> $fields
     *
     * @return void
     */
    public static function setKeyFields(array $fields): void
    {
        static::$keyFields = $fields;
    }
}
