<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Transformer;

/**
 * Global registry for type-based value transformers.
 *
 * Casters convert raw values (from arrays) into objects of a given type during
 * `fromArray()`. Serializers convert objects back into arrays/scalars during
 * `toArray()`. Both registries are keyed by class or interface name and support
 * inheritance: registering a caster for a parent class or interface will apply
 * to subclasses/implementors unless a more specific entry takes precedence.
 *
 * Per-field `factory` and `serialize` metadata configured on the DTO schema
 * always take precedence over the global registry entries.
 *
 * Example usage:
 * ```php
 * use PhpCollective\Dto\Transformer\TransformerRegistry;
 *
 * TransformerRegistry::addCaster(
 *     \DateTimeImmutable::class,
 *     fn(string $value) => new \DateTimeImmutable($value),
 * );
 *
 * TransformerRegistry::addSerializer(
 *     \DateTimeInterface::class,
 *     fn(\DateTimeInterface $value) => $value->format(DATE_ATOM),
 * );
 * ```
 */
class TransformerRegistry
{
    /**
     * @var array<string, callable>
     */
    private static array $casters = [];

    /**
     * @var array<string, callable>
     */
    private static array $serializers = [];

    /**
     * Register a caster for a type. The caster receives the raw value and must
     * return an instance of the target type (or compatible).
     *
     * @param string $type Class or interface name.
     * @param callable $caster Signature: `function(mixed $value): object`
     *
     * @return void
     */
    public static function addCaster(string $type, callable $caster): void
    {
        self::$casters[self::normalize($type)] = $caster;
    }

    /**
     * Register a serializer for a type. The serializer receives the object and
     * returns its array/scalar representation for `toArray()`.
     *
     * @param string $type Class or interface name.
     * @param callable $serializer Signature: `function(object $value): mixed`
     *
     * @return void
     */
    public static function addSerializer(string $type, callable $serializer): void
    {
        self::$serializers[self::normalize($type)] = $serializer;
    }

    /**
     * Remove a caster for a specific type.
     *
     * @param string $type
     *
     * @return void
     */
    public static function removeCaster(string $type): void
    {
        unset(self::$casters[self::normalize($type)]);
    }

    /**
     * Remove a serializer for a specific type.
     *
     * @param string $type
     *
     * @return void
     */
    public static function removeSerializer(string $type): void
    {
        unset(self::$serializers[self::normalize($type)]);
    }

    /**
     * Look up a caster for a declared field type. Exact match wins; otherwise
     * the first registered parent class or interface that `$type` is an
     * instance of is returned.
     *
     * @param string $type
     *
     * @return callable|null
     */
    public static function findCaster(string $type): ?callable
    {
        if (self::$casters === []) {
            return null;
        }

        $type = self::normalize($type);
        if (isset(self::$casters[$type])) {
            return self::$casters[$type];
        }

        if (!class_exists($type) && !interface_exists($type)) {
            return null;
        }

        foreach (self::$casters as $registered => $caster) {
            if ($registered === $type) {
                continue;
            }
            if ((class_exists($registered) || interface_exists($registered)) && is_a($type, $registered, true)) {
                return $caster;
            }
        }

        return null;
    }

    /**
     * Look up a serializer for an object instance. Exact match on the concrete
     * class wins; otherwise the first registered parent class or interface the
     * object is an instance of is returned.
     *
     * @param object $value
     *
     * @return callable|null
     */
    public static function findSerializer(object $value): ?callable
    {
        if (self::$serializers === []) {
            return null;
        }

        $class = $value::class;
        if (isset(self::$serializers[$class])) {
            return self::$serializers[$class];
        }

        foreach (self::$serializers as $registered => $serializer) {
            if ($registered === $class) {
                continue;
            }
            if ($value instanceof $registered) {
                return $serializer;
            }
        }

        return null;
    }

    /**
     * Check whether any caster or serializer is currently registered.
     * Used to bypass generated fast paths that would skip transformation.
     *
     * @return bool
     */
    public static function hasAny(): bool
    {
        return self::$casters !== [] || self::$serializers !== [];
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public static function hasCaster(string $type): bool
    {
        return isset(self::$casters[self::normalize($type)]);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public static function hasSerializer(string $type): bool
    {
        return isset(self::$serializers[self::normalize($type)]);
    }

    /**
     * Clear all registered casters and serializers. Primarily useful in tests.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$casters = [];
        self::$serializers = [];
    }

    /**
     * Normalize a type name by stripping any leading backslash.
     *
     * @param string $type
     *
     * @return string
     */
    private static function normalize(string $type): string
    {
        return ltrim($type, '\\');
    }
}
