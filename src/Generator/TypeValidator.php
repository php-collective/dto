<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use PhpCollective\Dto\Utility\Inflector;

/**
 * Validates types used in DTO definitions.
 */
class TypeValidator
{
    /**
     * @var array<string>
     */
    protected array $simpleTypeWhitelist = [
        'int',
        'float',
        'string',
        'bool',
        'callable',
        'iterable',
        'object',
    ];

    /**
     * @var array<string>
     */
    protected array $simpleTypeAdditionsForDocBlock = [
        'resource',
        'mixed',
    ];

    /**
     * Check if a type is valid for use in a DTO field.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isValidType(string $type): bool
    {
        if ($this->isValidSimpleType($type, $this->simpleTypeAdditionsForDocBlock)) {
            return true;
        }
        if ($this->isValidDto($type) || $this->isValidInterfaceOrClass($type)) {
            return true;
        }
        if ($this->isValidArray($type) || $this->isValidCollection($type)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a name is valid for a DTO field.
     *
     * @param string $name
     *
     * @return bool
     */
    public function isValidName(string $name): bool
    {
        return (bool)preg_match('#^[a-zA-Z][a-zA-Z0-9]+$#', $name);
    }

    /**
     * Check if a name is a valid DTO name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function isValidDto(string $name): bool
    {
        if (!preg_match('#^[A-Z][a-zA-Z0-9/]+$#', $name)) {
            return false;
        }

        $pieces = explode('/', $name);
        foreach ($pieces as $piece) {
            $expected = Inflector::camelize(Inflector::underscore($piece));
            if ($piece !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a type is a valid array type.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isValidArray(string $type): bool
    {
        if ($type === 'array') {
            return true;
        }

        if (substr($type, -2) !== '[]') {
            return false;
        }

        $type = substr($type, 0, -2);
        if (substr($type, 0, 1) === '?') {
            $type = substr($type, 1);
        }

        return $this->isValidSimpleType($type) || $this->isValidDto($type) || $this->isValidInterfaceOrClass($type);
    }

    /**
     * Check if a type is a valid collection type.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isValidCollection(string $type): bool
    {
        if ($type === 'array') {
            return true;
        }

        if (substr($type, -2) !== '[]') {
            return false;
        }

        $type = substr($type, 0, -2);

        return $this->isValidSimpleType($type) || $this->isValidDto($type) || $this->isValidInterfaceOrClass($type);
    }

    /**
     * Check if a type is a valid interface or class.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isValidInterfaceOrClass(string $type): bool
    {
        if (substr($type, 0, 1) !== '\\') {
            return false;
        }

        return interface_exists($type) || class_exists($type);
    }

    /**
     * Check if a type is a valid simple (scalar) type.
     *
     * @param string $type
     * @param array<string> $additional Additional types to allow
     *
     * @return bool
     */
    public function isValidSimpleType(string $type, array $additional = []): bool
    {
        $whitelist = array_merge($this->simpleTypeWhitelist, $additional);
        $types = explode('|', $type);

        // Non-union simple types with brackets are arrays
        if (count($types) === 1 && str_ends_with($types[0], '[]')) {
            return false;
        }

        $types = array_map(function ($value) {
            return !str_ends_with($value, '[]') ? $value : substr($value, 0, -2);
        }, $types);

        foreach ($types as $t) {
            if (!in_array($t, $whitelist, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the simple type whitelist.
     *
     * @return array<string>
     */
    public function getSimpleTypeWhitelist(): array
    {
        return $this->simpleTypeWhitelist;
    }

    /**
     * Get additional types allowed in docblocks.
     *
     * @return array<string>
     */
    public function getSimpleTypeAdditionsForDocBlock(): array
    {
        return $this->simpleTypeAdditionsForDocBlock;
    }
}
