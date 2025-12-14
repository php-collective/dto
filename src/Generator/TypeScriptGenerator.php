<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Generates TypeScript interfaces from DTO definitions.
 */
class TypeScriptGenerator
{
    /**
     * @var array<string, string>
     */
    protected const TYPE_MAP = [
        'int' => 'number',
        'integer' => 'number',
        'float' => 'number',
        'double' => 'number',
        'string' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'any[]',
        'mixed' => 'unknown',
        'object' => 'Record<string, unknown>',
        'callable' => '(...args: any[]) => any',
        'resource' => 'unknown',
        'iterable' => 'Iterable<unknown>',
        'null' => 'null',
        'void' => 'void',
    ];

    /**
     * @var array<string, string>
     */
    protected const DATE_TYPES = [
        '\\DateTime' => 'string',
        '\\DateTimeImmutable' => 'string',
        '\\DateTimeInterface' => 'string',
        'DateTime' => 'string',
        'DateTimeImmutable' => 'string',
        'DateTimeInterface' => 'string',
    ];

    protected IoInterface $io;

    /**
     * @var array<string, mixed>
     */
    protected array $options;

    /**
     * @param \PhpCollective\Dto\Generator\IoInterface $io
     * @param array<string, mixed> $options
     */
    public function __construct(IoInterface $io, array $options = [])
    {
        $this->io = $io;
        $this->options = $options + [
            'singleFile' => true,
            'fileNameCase' => 'pascal', // pascal, dashed, snake
            'readonly' => false,
            'strictNulls' => false,
            'exportStyle' => 'interface', // interface, type
            'dateType' => 'string', // string, Date
            'suffix' => 'Dto',
        ];
    }

    /**
     * Generate TypeScript from DTO definitions.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param string $outputPath
     *
     * @return int Number of files generated
     */
    public function generate(array $definitions, string $outputPath): int
    {
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $outputPath = rtrim($outputPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($this->options['singleFile']) {
            return $this->generateSingleFile($definitions, $outputPath);
        }

        return $this->generateMultipleFiles($definitions, $outputPath);
    }

    /**
     * Generate all interfaces in a single file.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param string $outputPath
     *
     * @return int
     */
    protected function generateSingleFile(array $definitions, string $outputPath): int
    {
        $content = $this->generateHeader();
        $content .= "\n";

        // Sort definitions to ensure dependencies come first
        $sorted = $this->sortByDependencies($definitions);

        foreach ($sorted as $name => $definition) {
            $content .= $this->generateInterface($name, $definition);
            $content .= "\n";
        }

        $fileName = 'dto.ts';
        $filePath = $outputPath . $fileName;

        file_put_contents($filePath, $content);
        $this->io->success("Generated: {$fileName}");

        return 1;
    }

    /**
     * Generate each interface in its own file.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param string $outputPath
     *
     * @return int
     */
    protected function generateMultipleFiles(array $definitions, string $outputPath): int
    {
        $count = 0;

        foreach ($definitions as $name => $definition) {
            $content = $this->generateHeader();
            $content .= "\n";

            // Add imports for referenced DTOs
            $imports = $this->getImports($definition, $definitions);
            if ($imports) {
                $content .= $imports . "\n";
            }

            $content .= $this->generateInterface($name, $definition);

            $fileName = $this->getFileName($name);
            $filePath = $outputPath . $fileName;

            file_put_contents($filePath, $content);
            $this->io->success("Generated: {$fileName}");
            $count++;
        }

        // Generate index file
        $indexContent = $this->generateIndexFile($definitions);
        file_put_contents($outputPath . 'index.ts', $indexContent);
        $this->io->success('Generated: index.ts');

        return $count + 1;
    }

    /**
     * Generate file header.
     *
     * @return string
     */
    protected function generateHeader(): string
    {
        return "// Auto-generated TypeScript definitions from php-collective/dto\n// Do not edit directly - regenerate from DTO configuration\n";
    }

    /**
     * Generate a single interface.
     *
     * @param string $name
     * @param array<string, mixed> $definition
     *
     * @return string
     */
    protected function generateInterface(string $name, array $definition): string
    {
        $interfaceName = $this->getInterfaceName($name);
        $readonly = $this->options['readonly'] ? 'readonly ' : '';
        $exportStyle = $this->options['exportStyle'];

        $fields = $definition['fields'] ?? [];
        $immutable = $definition['immutable'] ?? false;

        // Use readonly for immutable DTOs
        if ($immutable) {
            $readonly = 'readonly ';
        }

        $lines = [];

        if ($exportStyle === 'type') {
            $lines[] = "export type {$interfaceName} = {";
        } else {
            $lines[] = "export interface {$interfaceName} {";
        }

        foreach ($fields as $fieldName => $field) {
            $tsType = $this->mapType($field);
            $required = $field['required'] ?? false;
            $optional = $required ? '' : '?';

            // Add null to type if not required and not using optional syntax
            if (!$required && $this->options['strictNulls']) {
                $optional = '';
                $tsType = $tsType . ' | null';
            }

            $lines[] = "    {$readonly}{$fieldName}{$optional}: {$tsType};";
        }

        if ($exportStyle === 'type') {
            $lines[] = '};';
        } else {
            $lines[] = '}';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Map PHP type to TypeScript type.
     *
     * @param array<string, mixed> $field
     *
     * @return string
     */
    protected function mapType(array $field): string
    {
        $type = $field['type'] ?? 'mixed';
        $isCollection = $field['collection'] ?? false;

        // Handle array notation (e.g., "string[]", "int[]")
        if (str_ends_with($type, '[]')) {
            $innerType = substr($type, 0, -2);
            $mappedInner = $this->mapScalarType($innerType);

            return $mappedInner . '[]';
        }

        // Handle collections
        if ($isCollection) {
            $singular = $field['singular'] ?? null;
            $singularClass = $field['singularClass'] ?? null;

            if ($singularClass) {
                $innerType = $this->getInterfaceName($this->extractClassName($singularClass));

                return $innerType . '[]';
            }

            $mappedType = $this->mapScalarType($type);

            return $mappedType . '[]';
        }

        return $this->mapScalarType($type);
    }

    /**
     * Map a scalar or class type.
     *
     * @param string $type
     *
     * @return string
     */
    protected function mapScalarType(string $type): string
    {
        // Handle union types (PHP 8.0+)
        if (str_contains($type, '|')) {
            $types = explode('|', $type);
            $mappedTypes = array_map(fn ($t) => $this->mapSingleScalarType(trim($t)), $types);

            // Deduplicate (e.g., int|float both map to number)
            $mappedTypes = array_unique($mappedTypes);

            return implode(' | ', $mappedTypes);
        }

        return $this->mapSingleScalarType($type);
    }

    /**
     * Map a single scalar or class type.
     *
     * @param string $type
     *
     * @return string
     */
    protected function mapSingleScalarType(string $type): string
    {
        // Check scalar types
        $lowerType = strtolower($type);
        if (isset(self::TYPE_MAP[$lowerType])) {
            return self::TYPE_MAP[$lowerType];
        }

        // Check date types
        if (isset(self::DATE_TYPES[$type])) {
            return $this->options['dateType'] === 'Date' ? 'Date' : 'string';
        }

        // Handle FQCN (starts with backslash)
        if (str_starts_with($type, '\\')) {
            $className = $this->extractClassName($type);

            // Check if it's a date type
            if (isset(self::DATE_TYPES[$className])) {
                return $this->options['dateType'] === 'Date' ? 'Date' : 'string';
            }

            return $this->getInterfaceName($className);
        }

        // Assume it's a DTO reference
        return $this->getInterfaceName($type);
    }

    /**
     * Extract class name from FQCN.
     *
     * @param string $fqcn
     *
     * @return string
     */
    protected function extractClassName(string $fqcn): string
    {
        $parts = explode('\\', trim($fqcn, '\\'));

        return end($parts);
    }

    /**
     * Get the TypeScript interface name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getInterfaceName(string $name): string
    {
        $suffix = $this->options['suffix'];

        // Remove existing Dto suffix if present
        if (str_ends_with($name, 'Dto')) {
            $name = substr($name, 0, -3);
        }

        return $name . $suffix;
    }

    /**
     * Get the file name for a DTO.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getFileName(string $name): string
    {
        $interfaceName = $this->getInterfaceName($name);

        return match ($this->options['fileNameCase']) {
            'dashed' => $this->toDashed($interfaceName) . '.ts',
            'snake' => $this->toSnake($interfaceName) . '.ts',
            default => $interfaceName . '.ts',
        };
    }

    /**
     * Convert to dashed-case.
     *
     * @param string $name
     *
     * @return string
     */
    protected function toDashed(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name) ?? $name);
    }

    /**
     * Convert to snake_case.
     *
     * @param string $name
     *
     * @return string
     */
    protected function toSnake(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name) ?? $name);
    }

    /**
     * Get import statements for referenced DTOs.
     *
     * @param array<string, mixed> $definition
     * @param array<string, array<string, mixed>> $allDefinitions
     *
     * @return string
     */
    protected function getImports(array $definition, array $allDefinitions): string
    {
        $imports = [];
        $fields = $definition['fields'] ?? [];

        foreach ($fields as $field) {
            // Check if this field references another DTO
            $dtoRef = $field['dto'] ?? null;
            if ($dtoRef && isset($allDefinitions[$dtoRef])) {
                $interfaceName = $this->getInterfaceName($dtoRef);
                $fileName = $this->getFileName($dtoRef);
                $fileNameWithoutExt = substr($fileName, 0, -3);
                $imports[$interfaceName] = "import type { {$interfaceName} } from './{$fileNameWithoutExt}';";

                continue;
            }

            $type = $field['type'] ?? 'mixed';

            // Remove array notation
            if (str_ends_with($type, '[]')) {
                $type = substr($type, 0, -2);
            }

            // Skip scalar types
            if (isset(self::TYPE_MAP[strtolower($type)])) {
                continue;
            }

            // Skip date types
            if (isset(self::DATE_TYPES[$type])) {
                continue;
            }

            // Check if it's a reference to another DTO by name
            if (isset($allDefinitions[$type])) {
                $interfaceName = $this->getInterfaceName($type);
                $fileName = $this->getFileName($type);
                $fileNameWithoutExt = substr($fileName, 0, -3);
                $imports[$interfaceName] = "import type { {$interfaceName} } from './{$fileNameWithoutExt}';";

                continue;
            }

            // Check FQCN - extract class name and check
            $cleanType = ltrim($type, '\\');
            $className = $this->extractClassName($cleanType);

            // Check without Dto suffix
            $baseName = str_ends_with($className, 'Dto') ? substr($className, 0, -3) : $className;
            if (isset($allDefinitions[$baseName])) {
                $interfaceName = $this->getInterfaceName($baseName);
                $fileName = $this->getFileName($baseName);
                $fileNameWithoutExt = substr($fileName, 0, -3);
                $imports[$interfaceName] = "import type { {$interfaceName} } from './{$fileNameWithoutExt}';";

                continue;
            }

            // Check singularClass for collections
            $singularClass = $field['singularClass'] ?? null;
            if ($singularClass) {
                $singularClassName = $this->extractClassName($singularClass);
                $singularBaseName = str_ends_with($singularClassName, 'Dto') ? substr($singularClassName, 0, -3) : $singularClassName;
                if (isset($allDefinitions[$singularBaseName])) {
                    $interfaceName = $this->getInterfaceName($singularBaseName);
                    $fileName = $this->getFileName($singularBaseName);
                    $fileNameWithoutExt = substr($fileName, 0, -3);
                    $imports[$interfaceName] = "import type { {$interfaceName} } from './{$fileNameWithoutExt}';";
                }
            }
        }

        return implode("\n", array_values($imports));
    }

    /**
     * Generate index file that exports all interfaces.
     *
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return string
     */
    protected function generateIndexFile(array $definitions): string
    {
        $lines = [$this->generateHeader(), ''];

        foreach (array_keys($definitions) as $name) {
            $fileName = $this->getFileName($name);
            $fileNameWithoutExt = substr($fileName, 0, -3);
            $lines[] = "export * from './{$fileNameWithoutExt}';";
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Sort definitions by dependencies (referenced DTOs come first).
     *
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, array<string, mixed>>
     */
    protected function sortByDependencies(array $definitions): array
    {
        $sorted = [];
        $remaining = $definitions;
        $maxIterations = count($definitions) * 2;
        $iteration = 0;

        while ($remaining && $iteration < $maxIterations) {
            $iteration++;

            foreach ($remaining as $name => $definition) {
                $deps = $this->getDependencies($definition, $definitions);
                $allDepsResolved = true;

                foreach ($deps as $dep) {
                    if (isset($remaining[$dep])) {
                        $allDepsResolved = false;

                        break;
                    }
                }

                if ($allDepsResolved) {
                    $sorted[$name] = $definition;
                    unset($remaining[$name]);
                }
            }
        }

        // Add any remaining (circular dependencies)
        foreach ($remaining as $name => $definition) {
            $sorted[$name] = $definition;
        }

        return $sorted;
    }

    /**
     * Get dependencies for a definition.
     *
     * @param array<string, mixed> $definition
     * @param array<string, array<string, mixed>> $allDefinitions
     *
     * @return array<string>
     */
    protected function getDependencies(array $definition, array $allDefinitions): array
    {
        $deps = [];
        $fields = $definition['fields'] ?? [];

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'mixed';

            // Remove array notation
            if (str_ends_with($type, '[]')) {
                $type = substr($type, 0, -2);
            }

            // Skip scalar types
            if (isset(self::TYPE_MAP[strtolower($type)])) {
                continue;
            }

            // Check if it's a reference to another DTO
            if (isset($allDefinitions[$type])) {
                $deps[] = $type;
            }

            // Check singularClass
            $singularClass = $field['singularClass'] ?? null;
            if ($singularClass) {
                $className = $this->extractClassName($singularClass);
                if (isset($allDefinitions[$className])) {
                    $deps[] = $className;
                }
            }
        }

        return array_unique($deps);
    }
}
