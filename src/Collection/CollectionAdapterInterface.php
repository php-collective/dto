<?php
declare(strict_types=1);

namespace PhpCollective\Dto\Collection;

/**
 * Interface for collection type adapters.
 *
 * This allows framework-specific collection types (Cake\Collection, Laravel Collection,
 * Doctrine ArrayCollection) to be used with generated DTOs without duplicating templates.
 *
 * Each adapter defines how to:
 * - Create a new empty collection
 * - Append items to a collection (mutable or immutable)
 * - Get the collection's FQCN for type hints
 */
interface CollectionAdapterInterface
{
    /**
     * Get the fully qualified class name of the collection type.
     *
     * @return string
     */
    public function getCollectionClass(): string;

    /**
     * Whether this collection type is immutable (append returns new instance).
     *
     * @return bool
     */
    public function isImmutable(): bool;

    /**
     * Get the method name used to append an item.
     *
     * @return string
     */
    public function getAppendMethod(): string;

    /**
     * Get PHP code to create a new empty collection.
     *
     * @param string $typeHint The collection type hint (e.g., '\ArrayObject')
     * @return string PHP code snippet
     */
    public function getCreateEmptyCode(string $typeHint): string;

    /**
     * Get PHP code to append an item to a collection.
     *
     * For mutable collections: `$collection->append($item);`
     * For immutable collections: `$collection = $collection->appendItem($item);`
     *
     * @param string $collectionVar The variable name (e.g., '$this->items')
     * @param string $itemVar The item variable name (e.g., '$item')
     * @return string PHP code snippet
     */
    public function getAppendCode(string $collectionVar, string $itemVar): string;
}
