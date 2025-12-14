<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

use InvalidArgumentException;
use RuntimeException;

/**
 * Engine for PHP array configuration files.
 *
 * PHP config files should return an array with DTO definitions:
 *
 * ```php
 * <?php
 * return [
 *     'User' => [
 *         'fields' => [
 *             'id' => ['type' => 'int', 'required' => true],
 *             'name' => 'string', // Shorthand for ['type' => 'string']
 *             'email' => ['type' => 'string', 'required' => true],
 *         ],
 *     ],
 * ];
 * ```
 */
class PhpEngine implements FileBasedEngineInterface
{
    /**
     * @var string
     */
    public const EXT = 'php';

    /**
     * @return string
     */
    public function extension(): string
    {
        return static::EXT;
    }

    /**
     * Validates files exist and are readable.
     *
     * @param array<string> $files
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function validate(array $files): void
    {
        foreach ($files as $file) {
            if (!is_readable($file)) {
                throw new RuntimeException("PHP config file not readable: {$file}");
            }
        }
    }

    /**
     * Parse is not used for PHP files - use parseFile() instead.
     *
     * @param string $content
     *
     * @throws \RuntimeException
     *
     * @return array<string, mixed>
     */
    public function parse(string $content): array
    {
        throw new RuntimeException(
            'PhpEngine does not support parsing string content. Use parseFile() instead.',
        );
    }

    /**
     * Parses a PHP file that returns an array.
     *
     * @param string $filePath
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("PHP config file not found: {$filePath}");
        }

        $result = require $filePath;

        if (!is_array($result)) {
            throw new InvalidArgumentException(
                "PHP config file must return an array: {$filePath}",
            );
        }

        /** @var array<string, mixed> $result */
        return $this->normalizeConfig($result);
    }

    /**
     * Normalizes config into standard format.
     *
     * @param array<string, mixed> $config
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function normalizeConfig(array $config): array
    {
        $result = [];

        foreach ($config as $name => $dto) {
            if (!is_array($dto)) {
                throw new InvalidArgumentException(
                    "DTO '{$name}' configuration must be an array",
                );
            }

            $dto['name'] = $name;

            $fieldsRaw = $dto['fields'] ?? [];
            if (!is_array($fieldsRaw)) {
                throw new InvalidArgumentException(
                    "DTO '{$name}' fields must be an array",
                );
            }
            /** @var array<string, mixed> $fields */
            $fields = $fieldsRaw;

            foreach ($fields as $key => $field) {
                // Support shorthand: 'fieldName' => 'type'
                if (!is_array($field)) {
                    $field = [
                        'type' => $field,
                    ];
                }
                $field['name'] = $key;
                $fields[$key] = $field;
            }

            $dto['fields'] = $fields;
            $result[$name] = $dto;
        }

        return $result;
    }
}
