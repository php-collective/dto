<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Collection;

use ArrayObject;

/**
 * Adapter for PHP's built-in ArrayObject collection.
 *
 * ArrayObject is mutable - append() modifies the collection in place.
 */
class ArrayObjectAdapter implements CollectionAdapterInterface
{
    /**
     * @inheritDoc
     */
    public function getCollectionClass(): string
    {
        return ArrayObject::class;
    }

    /**
     * @inheritDoc
     */
    public function isImmutable(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getAppendMethod(): string
    {
        return 'append';
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
        return "{$collectionVar}->append({$itemVar});";
    }
}
