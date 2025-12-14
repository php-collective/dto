<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

use InvalidArgumentException;
use Nette\Neon\Exception;
use Nette\Neon\Neon;

class NeonEngine implements EngineInterface
{
 /**
  * @var string
  */
    public const EXT = 'neon';

    /**
     * @return string
     */
    public function extension(): string
    {
        return static::EXT;
    }

    /**
     * Validates files against JSON schema.
     *
     * Requires justinrainbow/json-schema to be installed.
     * If not available, validation is skipped.
     *
     * @param array<string> $files
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function validate(array $files): void
    {
        if (!JsonSchemaValidator::isAvailable()) {
            return;
        }

        foreach ($files as $file) {
            if (!is_readable($file)) {
                throw new InvalidArgumentException("Cannot read file: {$file}");
            }

            $content = file_get_contents($file);
            if ($content === false) {
                throw new InvalidArgumentException("Cannot read file: {$file}");
            }

            try {
                $data = Neon::decode($content);
            } catch (Exception $e) {
                throw new InvalidArgumentException("Invalid NEON file: {$file} - " . $e->getMessage(), $e->getCode(), $e);
            }

            if ($data === null) {
                throw new InvalidArgumentException("Invalid NEON file: {$file}");
            }

            JsonSchemaValidator::validate($data, $file);
        }
    }

    /**
     * Parses content into array form. Can also already contain basic validation
     * if validate() cannot be used.
     *
     * @param string $content
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public function parse(string $content): array
    {
        $result = [];

        try {
            $result = Neon::decode($content);
        } catch (Exception $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        if ($result === null) {
            throw new InvalidArgumentException(sprintf('%s: invalid neon content.', static::class));
        }

        foreach ($result as $name => $dto) {
            $dto['name'] = $name;

            $fields = $dto['fields'] ?? [];
            foreach ($fields as $key => $field) {
                if (is_string($field)) {
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
