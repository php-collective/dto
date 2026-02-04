<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer\Ref;

use JsonException;

class FileRefResolver implements RefResolverInterface
{
    protected ?string $basePath;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $cache = [];

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath;
    }

    /**
     * @inheritDoc
     */
    public function resolve(string $ref, array $options = []): ?array
    {
        if (str_starts_with($ref, '#/')) {
            return null;
        }

        $scheme = parse_url($ref, PHP_URL_SCHEME);
        if ($scheme !== null) {
            return null;
        }

        [$path, $fragment] = $this->splitRef($ref);
        if ($path === null) {
            return null;
        }

        $resolvedPath = $this->resolvePath($path, $options);
        if ($resolvedPath === null) {
            return null;
        }

        $document = $this->loadDocument($resolvedPath);
        if ($document === null) {
            return null;
        }

        $schema = $this->resolveFragment($document, $fragment);
        if ($schema === null || !is_array($schema)) {
            return null;
        }

        return [
            'schema' => $schema,
            'definitionsSource' => $document,
            'sourcePath' => $resolvedPath,
            'fragment' => $fragment,
        ];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    protected function splitRef(string $ref): array
    {
        $parts = explode('#', $ref, 2);
        $path = $parts[0] ?? '';
        $fragment = $parts[1] ?? '';

        if ($path === '') {
            return [null, $fragment];
        }

        return [$path, $fragment];
    }

    protected function resolvePath(string $path, array $options): ?string
    {
        if ($this->isAbsolutePath($path)) {
            return is_file($path) ? $path : null;
        }

        $basePath = $options['basePath'] ?? $this->basePath;
        if ($basePath === null) {
            return null;
        }

        if (is_file($basePath)) {
            $basePath = dirname($basePath);
        }

        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $path;

        return is_file($fullPath) ? $fullPath : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadDocument(string $path): ?array
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($document)) {
            return null;
        }

        $this->cache[$path] = $document;

        return $document;
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>|null
     */
    protected function resolveFragment(array $document, string $fragment): ?array
    {
        if ($fragment === '' || $fragment === '#') {
            return $document;
        }

        if (!str_starts_with($fragment, '/')) {
            return null;
        }

        $pointer = rawurldecode($fragment);
        $segments = explode('/', ltrim($pointer, '/'));
        $current = $document;

        foreach ($segments as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return is_array($current) ? $current : null;
    }

    protected function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        return (bool)preg_match('~^[A-Za-z]:[\\\\/]~', $path);
    }
}
