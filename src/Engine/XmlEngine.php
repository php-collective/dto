<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

use PhpCollective\Dto\Utility\XmlParser;

class XmlEngine implements EngineInterface
{
    /**
     * @var string
     */
    public const EXT = 'xml';

    /**
     * @return string
     */
    public function extension(): string
    {
        return static::EXT;
    }

    /**
     * Validates files.
     *
     * @param array<string> $files
     *
     * @return void
     */
    public function validate(array $files): void
    {
        foreach ($files as $file) {
            XmlValidator::validate($file);
        }
    }

    /**
     * Parses content into array form. Can also already contain basic validation
     * if validate() cannot be used.
     *
     * @param string $content
     *
     * @return array
     */
    public function parse(string $content): array
    {
        $xml = XmlParser::build($content);
        $array = XmlParser::toArray($xml);

        if (!isset($array['dtos']['dto'])) {
            return [];
        }

        $dtos = !isset($array['dtos']['dto'][0]) ? [$array['dtos']['dto']] : $array['dtos']['dto'];
        $result = [];
        foreach ($dtos as $dto) {
            $name = $dto['@name'];

            foreach ($dto as $key => $value) {
                if (mb_substr($key, 0, 1) !== '@') {
                    continue;
                }

                $key = mb_substr($key, 1);
                $value = $this->castBoolValue($value, $key);

                $result[$name][$key] = $value;
            }

            if (!isset($dto['field'])) {
                $result[$name]['fields'] = [];

                continue;
            }

            if (!isset($dto['field'][0])) {
                $dto['field'] = [$dto['field']];
            }

            $fields = [];
            foreach ($dto['field'] as $fieldDefinition) {
                $fieldName = $fieldDefinition['@name'];
                foreach ($fieldDefinition as $k => $v) {
                    $key = substr($k, 1);
                    $v = $this->castBoolValue($v, $key);
                    $v = $this->castDefaultValue($v, $key, $fieldDefinition);

                    $fields[$fieldName][$key] = $v;
                }
            }
            $result[$name]['fields'] = $fields;
        }

        return $result;
    }

    /**
     * @param string|float|int|bool $value
     * @param string|null $key
     *
     * @return string|float|int|bool
     */
    protected function castBoolValue(string|float|int|bool $value, ?string $key = null): string|float|int|bool
    {
        if ($key && !in_array($key, ['required', 'immutable', 'collection', 'associative'], true)) {
            return $value;
        }

        if ($value === 'true' || $value === '1') {
            return true;
        }
        if ($value === 'false' || $value === '0' || $value === '') {
            return false;
        }

        return $value;
    }

    /**
     * @param string|float|int|bool $value
     * @param string $key
     * @param array<string, mixed> $fieldDefinition
     *
     * @return string|float|int|bool
     */
    protected function castDefaultValue($value, string $key, array $fieldDefinition)
    {
        if (!in_array($key, ['defaultValue'], true) || empty($fieldDefinition['@type'])) {
            return $value;
        }

        $type = $fieldDefinition['@type'];
        if ($type === 'int') {
            return (int)$value;
        }
        if ($type === 'float') {
            return (float)$value;
        }
        if ($type === 'bool') {
            return $this->castBoolValue($value);
        }

        return $value;
    }
}
