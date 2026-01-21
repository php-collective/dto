<?php
declare(strict_types=1);

namespace PhpCollective\Dto\Collection;

/**
 * Adapter for CakePHP's Collection class.
 *
 * Cake\Collection\Collection is immutable - appendItem() returns a NEW collection instance.
 * This is different from ArrayObject which mutates in place.
 *
 * Usage in CakePHP projects:
 * ```php
 * // In bootstrap or service provider
 * CollectionAdapterRegistry::register(new CakeCollectionAdapter());
 * ```
 */
class CakeCollectionAdapter implements CollectionAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getCollectionClass(): string
    {
        return '\\Cake\\Collection\\Collection';
    }

    /**
     * @inheritDoc
     */
    public function isImmutable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getAppendMethod(): string
    {
        return 'appendItem';
    }

    /**
     * @inheritDoc
     */
    public function getCreateEmptyCode(string $typeHint): string
    {
        return "new {$typeHint}([])";
    }

    /**
     * @inheritDoc
     */
    public function getAppendCode(string $collectionVar, string $itemVar): string
    {
        // Cake's appendItem() returns a new Collection instance
        return "{$collectionVar} = {$collectionVar}->appendItem({$itemVar});";
    }
}
