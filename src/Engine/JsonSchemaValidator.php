<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

use InvalidArgumentException;
use JsonSchema\Validator;

class JsonSchemaValidator
{
    /**
     * Path to the JSON schema file.
     *
     * @var string|null
     */
    protected static ?string $schemaPath = null;

    /**
     * Check if JSON Schema validation is available.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return class_exists(Validator::class);
    }

    /**
     * Set the path to the JSON schema file.
     *
     * @param string $path
     *
     * @return void
     */
    public static function setSchemaPath(string $path): void
    {
        static::$schemaPath = $path;
    }

    /**
     * Get the path to the JSON schema file.
     *
     * @return string
     */
    public static function getSchemaPath(): string
    {
        if (static::$schemaPath !== null) {
            return static::$schemaPath;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'dto.schema.json';
    }

    /**
     * Validate data against the JSON schema.
     *
     * @param array<string, mixed> $data The parsed data to validate
     * @param string $file The source file path (for error messages)
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public static function validate(array $data, string $file): void
    {
        if (!static::isAvailable()) {
            return;
        }

        // Empty data is valid - nothing to validate
        if ($data === []) {
            return;
        }

        $schemaPath = static::getSchemaPath();
        $schemaContent = file_get_contents($schemaPath);
        if ($schemaContent === false) {
            throw new InvalidArgumentException("Cannot read schema file: {$schemaPath}");
        }

        $schema = json_decode($schemaContent);
        if ($schema === null) {
            throw new InvalidArgumentException("Invalid JSON schema file: {$schemaPath}");
        }

        $dataObject = json_decode((string)json_encode($data));

        $validator = new Validator();
        $validator->validate($dataObject, $schema);

        if (!$validator->isValid()) {
            $errors = static::formatErrors($validator->getErrors(), $file);

            throw new InvalidArgumentException(implode("\n", $errors));
        }
    }

    /**
     * Format validation errors.
     *
     * @param array<array<string, mixed>> $errors
     * @param string $file
     *
     * @return array<string>
     */
    protected static function formatErrors(array $errors, string $file): array
    {
        $result = [];
        foreach ($errors as $error) {
            $property = $error['property'] ?? '';
            $message = $error['message'] ?? 'Unknown error';

            $result[] = sprintf(
                'Error in `%s`: [%s] %s',
                $file,
                $property,
                $message,
            );
        }

        return $result;
    }
}
