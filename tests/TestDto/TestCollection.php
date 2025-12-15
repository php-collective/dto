<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\TestDto;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * A simple Collection class mimicking CakePHP/Laravel Collection for testing.
 *
 * @template T
 * @implements \IteratorAggregate<int, T>
 */
class TestCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<int, T>
     */
    protected array $items;

    /**
     * @param array<int, T> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return array<int, T>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Filter items using a callback (like CakePHP/Laravel).
     *
     * @param callable(T): bool $callback
     *
     * @return static
     */
    public function filter(callable $callback): static
    {
        return new static(array_values(array_filter($this->items, $callback)));
    }

    /**
     * Map items using a callback (like CakePHP/Laravel).
     *
     * @template U
     *
     * @param callable(T): U $callback
     *
     * @return static
     */
    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Get first item (like CakePHP/Laravel).
     *
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Check if collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return count($this->items) === 0;
    }
}
