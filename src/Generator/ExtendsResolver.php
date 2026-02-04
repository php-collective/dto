<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

use InvalidArgumentException;
use PhpCollective\Dto\Dto\AbstractDto;
use PhpCollective\Dto\Dto\AbstractImmutableDto;
use ReflectionClass;

/**
 * Resolves and validates DTO inheritance (extends attribute).
 */
class ExtendsResolver
{
    /**
     * @var string
     */
    protected string $suffix;

    /**
     * @param string $suffix
     */
    public function __construct(string $suffix)
    {
        $this->suffix = $suffix;
    }

    /**
     * Resolve extends references for all DTOs.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function resolve(array $config): array
    {
        foreach ($config as $name => $dto) {
            $config[$name] = $this->resolveExtends($dto, $config);
        }

        return $config;
    }

    /**
     * Resolve extends for a single DTO.
     *
     * @param array<string, mixed> $dto
     * @param array<string, mixed> $allDtos
     *
     * @return array<string, mixed>
     */
    protected function resolveExtends(array $dto, array $allDtos): array
    {
        $extends = $dto['extends'];

        if (in_array($extends, ['\\PhpCollective\\Dto\\Dto\\AbstractDto', '\\PhpCollective\\Dto\\Dto\\AbstractImmutableDto'], true)) {
            return $dto;
        }

        $isImmutable = !empty($dto['immutable']);

        if (isset($allDtos[$extends])) {
            $dto = $this->resolveInternalExtends($dto, $extends, $allDtos, $isImmutable);
        } else {
            $dto = $this->resolveExternalExtends($dto, $extends, $isImmutable);
        }

        return $dto;
    }

    /**
     * Resolve extends to another DTO in the same config.
     *
     * @param array<string, mixed> $dto
     * @param string $extends
     * @param array<string, mixed> $allDtos
     * @param bool $isImmutable
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function resolveInternalExtends(array $dto, string $extends, array $allDtos, bool $isImmutable): array
    {
        $dto['extends'] = $extends . $this->suffix;

        if (!$isImmutable && !empty($allDtos[$extends]['immutable'])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid `extends` attribute for `%s` DTO: cannot extend immutable DTO `%s`.\n"
                . 'Hint: Either make `%s` immutable, or extend a mutable DTO instead.',
                $dto['name'],
                $extends,
                $dto['name'],
            ));
        }

        if ($isImmutable && empty($allDtos[$extends]['immutable'])) {
            throw new InvalidArgumentException(sprintf(
                "Invalid `extends` attribute for `%s` DTO: immutable DTO cannot extend mutable DTO `%s`.\n"
                . 'Hint: Either make `%s` mutable, or make `%s` immutable.',
                $dto['name'],
                $extends,
                $dto['name'],
                $extends,
            ));
        }

        return $dto;
    }

    /**
     * Resolve extends to an external class.
     *
     * @param array<string, mixed> $dto
     * @param string $extends
     * @param bool $isImmutable
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function resolveExternalExtends(array $dto, string $extends, bool $isImmutable): array
    {
        if (!class_exists($extends)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid `extends` attribute for `%s` DTO: class `%s` does not exist.\n"
                . 'Hint: Check the class name spelling and ensure the class is autoloadable.',
                $dto['name'],
                $extends,
            ));
        }

        $extendedDtoReflectionClass = new ReflectionClass($extends);

        if ($extendedDtoReflectionClass->getParentClass() === false) {
            throw new InvalidArgumentException(sprintf(
                "Invalid `extends` attribute for `%s` DTO: `%s` must extend %s.\n"
                . 'Hint: The parent class should be a DTO class extending the appropriate base.',
                $dto['name'],
                $extends,
                $isImmutable ? AbstractImmutableDto::class : AbstractDto::class,
            ));
        }

        if ($isImmutable && !$extendedDtoReflectionClass->isSubclassOf(AbstractImmutableDto::class)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid `extends` attribute for `%s` DTO: `%s` is not immutable.\n"
                . 'Hint: Immutable DTOs must extend other immutable DTOs or AbstractImmutableDto.',
                $dto['name'],
                $extends,
            ));
        }

        if (!$isImmutable && !$extendedDtoReflectionClass->isSubclassOf(AbstractDto::class)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid `extends` attribute for `%s` DTO: `%s` is immutable.\n"
                . 'Hint: Mutable DTOs cannot extend immutable DTOs. Either make `%s` immutable or change the parent.',
                $dto['name'],
                $extends,
                $dto['name'],
            ));
        }

        return $dto;
    }

    /**
     * Resolve namespace paths in extends references.
     *
     * @param array<string, mixed> $config
     * @param string $namespace
     *
     * @return array<string, mixed>
     */
    public function resolveNamespacePaths(array $config, string $namespace): array
    {
        foreach ($config as $name => $dto) {
            if (strpos($dto['className'], '/') !== false) {
                $pieces = explode('/', $dto['className']);
                $dto['className'] = array_pop($pieces);
                $dto['namespace'] .= '\\' . implode('\\', $pieces);
            }

            if (!empty($dto['extends']) && strpos($dto['extends'], '/') !== false) {
                $pieces = explode('/', $dto['extends']);
                $dto['extends'] = '\\' . $namespace . '\Dto\\' . implode('\\', $pieces);
            }

            $config[$name] = $dto;
        }

        return $config;
    }
}
