<?php
declare(strict_types=1);

namespace PhpCollective\Dto\Collection;

/**
 * Registry for collection type adapters.
 *
 * Framework integrations can register their collection adapters here
 * to enable proper code generation for their collection types.
 *
 * Example usage in CakePHP:
 * ```php
 * // In CakeDtoPlugin::bootstrap()
 * CollectionAdapterRegistry::register(new CakeCollectionAdapter());
 * ```
 *
 * Example usage in Laravel:
 * ```php
 * // In AppServiceProvider::boot()
 * CollectionAdapterRegistry::register(new LaravelCollectionAdapter());
 * ```
 */
class CollectionAdapterRegistry
{
    /**
     * @var array<string, CollectionAdapterInterface>
     */
    private static array $adapters = [];

    /**
     * @var CollectionAdapterInterface|null
     */
    private static ?CollectionAdapterInterface $defaultAdapter = null;

    /**
     * Register a collection adapter.
     *
     * @param CollectionAdapterInterface $adapter
     * @return void
     */
    public static function register(CollectionAdapterInterface $adapter): void
    {
        static::$adapters[$adapter->getCollectionClass()] = $adapter;
    }

    /**
     * Get adapter for a collection type.
     *
     * @param string $collectionClass The collection class name
     * @return CollectionAdapterInterface|null
     */
    public static function get(string $collectionClass): ?CollectionAdapterInterface
    {
        // Normalize class name
        $normalized = ltrim($collectionClass, '\\');

        foreach (static::$adapters as $class => $adapter) {
            if (ltrim($class, '\\') === $normalized) {
                return $adapter;
            }
        }

        return null;
    }

    /**
     * Check if an adapter is registered for a collection type.
     *
     * @param string $collectionClass
     * @return bool
     */
    public static function has(string $collectionClass): bool
    {
        return static::get($collectionClass) !== null;
    }

    /**
     * Get or create adapter for a collection type.
     *
     * Returns the registered adapter if available, otherwise returns the default adapter.
     *
     * @param string $collectionClass
     * @return CollectionAdapterInterface
     */
    public static function getOrDefault(string $collectionClass): CollectionAdapterInterface
    {
        return static::get($collectionClass) ?? static::getDefaultAdapter();
    }

    /**
     * Set the default adapter for unregistered collection types.
     *
     * @param CollectionAdapterInterface $adapter
     * @return void
     */
    public static function setDefaultAdapter(CollectionAdapterInterface $adapter): void
    {
        static::$defaultAdapter = $adapter;
    }

    /**
     * Get the default adapter.
     *
     * @return CollectionAdapterInterface
     */
    public static function getDefaultAdapter(): CollectionAdapterInterface
    {
        if (static::$defaultAdapter === null) {
            static::$defaultAdapter = new ArrayObjectAdapter();
        }

        return static::$defaultAdapter;
    }

    /**
     * Get all registered adapters.
     *
     * @return array<string, CollectionAdapterInterface>
     */
    public static function all(): array
    {
        return static::$adapters;
    }

    /**
     * Clear all registered adapters (useful for testing).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$adapters = [];
        static::$defaultAdapter = null;
    }
}
