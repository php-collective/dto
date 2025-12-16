<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use RuntimeException;

/**
 * Analyzes DTO dependencies and detects circular references.
 */
class DependencyAnalyzer
{
    /**
     * @var string
     */
    protected string $suffix;

    /**
     * @param string $suffix
     */
    public function __construct(string $suffix = 'Dto')
    {
        $this->suffix = $suffix;
    }

    /**
     * Analyze DTOs for circular dependencies.
     *
     * @param array<string, array<string, mixed>> $dtos
     *
     * @return void
     */
    public function analyze(array $dtos): void
    {
        $graph = $this->buildDependencyGraph($dtos);

        foreach (array_keys($graph) as $dtoName) {
            $this->detectCycle($dtoName, $graph, [], []);
        }
    }

    /**
     * Build a dependency graph from DTO definitions.
     *
     * @param array<string, array<string, mixed>> $dtos
     *
     * @return array<string, array<string>>
     */
    protected function buildDependencyGraph(array $dtos): array
    {
        $graph = [];

        foreach ($dtos as $name => $dto) {
            $graph[$name] = $this->extractDependencies($dto, array_keys($dtos));
        }

        return $graph;
    }

    /**
     * Extract DTO dependencies from a DTO definition.
     *
     * @param array<string, mixed> $dto
     * @param array<string> $knownDtos
     *
     * @return array<string>
     */
    protected function extractDependencies(array $dto, array $knownDtos): array
    {
        $dependencies = [];
        $fields = $dto['fields'] ?? [];

        foreach ($fields as $field) {
            $type = $field['type'] ?? '';

            // Extract DTO name from type (handles Foo, Foo[], ?Foo[], etc.)
            $dtoName = $this->extractDtoNameFromType($type);
            if ($dtoName && in_array($dtoName, $knownDtos, true) && $dtoName !== $dto['name']) {
                $dependencies[] = $dtoName;
            }

            // Check singular type for collections
            $singularType = $field['singularType'] ?? null;
            if ($singularType) {
                $dtoName = $this->extractDtoNameFromType($singularType);
                if ($dtoName && in_array($dtoName, $knownDtos, true) && $dtoName !== $dto['name']) {
                    $dependencies[] = $dtoName;
                }
            }

            // Check dto field
            $dtoField = $field['dto'] ?? null;
            if ($dtoField && in_array($dtoField, $knownDtos, true) && $dtoField !== $dto['name']) {
                $dependencies[] = $dtoField;
            }
        }

        // Check extends
        if (!empty($dto['extends'])) {
            $extends = $dto['extends'];
            // Extract name from class path like \App\Dto\ParentDto or ParentDto
            $parentName = $this->extractDtoNameFromClassName($extends);
            if ($parentName && in_array($parentName, $knownDtos, true) && $parentName !== $dto['name']) {
                $dependencies[] = $parentName;
            }
        }

        return array_unique($dependencies);
    }

    /**
     * Extract DTO name from a type string.
     *
     * @param string $type
     *
     * @return string|null
     */
    protected function extractDtoNameFromType(string $type): ?string
    {
        // Remove array notation
        $type = rtrim($type, '[]');

        // Remove nullable prefix
        $type = ltrim($type, '?');

        // Remove namespace prefix for FQCN
        if (str_starts_with($type, '\\')) {
            return $this->extractDtoNameFromClassName($type);
        }

        // Check if it looks like a DTO name (PascalCase starting with uppercase)
        if (preg_match('#^[A-Z][a-zA-Z0-9/]+$#', $type)) {
            // Handle namespaced DTOs like Sub/ChildDto -> Sub/Child
            $parts = explode('/', $type);
            $lastPart = end($parts);
            if (str_ends_with($lastPart, $this->suffix)) {
                $parts[count($parts) - 1] = substr($lastPart, 0, -strlen($this->suffix));

                return implode('/', $parts);
            }

            return $type;
        }

        return null;
    }

    /**
     * Extract DTO name from a fully qualified class name.
     *
     * @param string $className
     *
     * @return string|null
     */
    protected function extractDtoNameFromClassName(string $className): ?string
    {
        $className = ltrim($className, '\\');
        $parts = explode('\\', $className);
        $shortName = end($parts);

        // Remove suffix if present
        if (str_ends_with($shortName, $this->suffix)) {
            return substr($shortName, 0, -strlen($this->suffix));
        }

        return null;
    }

    /**
     * Detect cycles in the dependency graph using DFS.
     *
     * @param string $node
     * @param array<string, array<string>> $graph
     * @param array<string> $path Current path being explored
     * @param array<string, bool> $visited Globally visited nodes
     *
     * @throws \RuntimeException If cycle is detected
     *
     * @return void
     */
    protected function detectCycle(string $node, array $graph, array $path, array $visited): void
    {
        // If we've already fully explored this node, skip it
        if (isset($visited[$node])) {
            return;
        }

        // If this node is in the current path, we have a cycle
        if (in_array($node, $path, true)) {
            $cycleStart = (int)array_search($node, $path, true);
            $cyclePath = array_slice($path, $cycleStart);
            $cyclePath[] = $node;

            throw new RuntimeException(sprintf(
                "Circular dependency detected: %s\n"
                . 'Hint: Consider making one of the fields nullable or using lazy loading to break the cycle.',
                implode(' -> ', $cyclePath),
            ));
        }

        // Add current node to path
        $path[] = $node;

        // Explore dependencies
        $dependencies = $graph[$node] ?? [];
        foreach ($dependencies as $dependency) {
            $this->detectCycle($dependency, $graph, $path, $visited);
        }

        // Mark as fully visited
        $visited[$node] = true;
    }

    /**
     * Get all dependencies for a DTO (including transitive).
     *
     * @param string $dtoName
     * @param array<string, array<string, mixed>> $dtos
     *
     * @return array<string>
     */
    public function getAllDependencies(string $dtoName, array $dtos): array
    {
        $graph = $this->buildDependencyGraph($dtos);
        $visited = [];
        $this->collectDependencies($dtoName, $graph, $visited);
        unset($visited[$dtoName]);

        return array_keys($visited);
    }

    /**
     * Collect dependencies recursively.
     *
     * @param string $node
     * @param array<string, array<string>> $graph
     * @param array<string, bool> $visited
     *
     * @return void
     */
    protected function collectDependencies(string $node, array $graph, array &$visited): void
    {
        if (isset($visited[$node])) {
            return;
        }

        $visited[$node] = true;

        foreach ($graph[$node] ?? [] as $dependency) {
            $this->collectDependencies($dependency, $graph, $visited);
        }
    }
}
