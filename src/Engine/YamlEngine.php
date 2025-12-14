<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

use InvalidArgumentException;

class YamlEngine implements EngineInterface
{
 /**
  * @var string
  */
    public const EXT = 'yml';

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

            $data = yaml_parse($content);
            if (!$data) {
                throw new InvalidArgumentException("Invalid YAML file: {$file}");
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
        $result = yaml_parse($content);
        if (!$result) {
            throw new InvalidArgumentException('Invalid YAML file');
        }

        foreach ($result as $name => $dto) {
            $dto['name'] = $name;

            $fields = $dto['fields'] ?? [];
            foreach ($fields as $key => $field) {
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
