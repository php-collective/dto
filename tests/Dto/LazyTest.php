<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Dto;

use PhpCollective\Dto\Test\TestDto\LazyDto;
use PhpCollective\Dto\Test\TestDto\SimpleDto;
use PHPUnit\Framework\TestCase;

class LazyTest extends TestCase
{
    public function testLazyDtoField(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Nested', 'count' => 5],
        ]);

        $this->assertSame('Test', $dto->getTitle());

        $nested = $dto->getNested();
        $this->assertInstanceOf(SimpleDto::class, $nested);
        $this->assertSame('Nested', $nested->getName());
        $this->assertSame(5, $nested->getCount());
    }

    public function testLazyCollectionField(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'items' => [
                ['name' => 'A', 'count' => 1],
                ['name' => 'B', 'count' => 2],
            ],
        ]);

        $items = $dto->getItems();
        $this->assertCount(2, $items);
        $this->assertInstanceOf(SimpleDto::class, $items[0]);
        $this->assertSame('A', $items[0]->getName());
        $this->assertSame('B', $items[1]->getName());
    }

    public function testLazyToArrayWithoutHydration(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Raw'],
        ]);

        $arr = $dto->toArray();
        $this->assertSame('Test', $arr['title']);
        $this->assertIsArray($arr['nested']);
        $this->assertSame('Raw', $arr['nested']['name']);
    }

    public function testLazyToArrayAfterHydration(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Hydrated'],
        ]);

        $dto->getNested();
        $arr = $dto->toArray();
        $this->assertSame('Hydrated', $arr['nested']['name']);
    }

    public function testLazyHasBeforeAndAfterAccess(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Sub'],
        ]);

        $this->assertTrue($dto->hasNested());
        $dto->getNested();
        $this->assertTrue($dto->hasNested());
    }

    public function testLazyNullField(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => null,
        ]);

        $this->assertNull($dto->getNested());
    }

    public function testLazyFieldNotSet(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
        ]);

        $this->assertFalse($dto->hasNested());
        $this->assertNull($dto->getNested());
    }

    public function testLazySetterClearsLazyData(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Original'],
        ]);

        $replacement = new SimpleDto(['name' => 'Replaced']);
        $dto->setNested($replacement);

        $this->assertSame('Replaced', $dto->getNested()->getName());
    }

    public function testLazyCollectionToArrayWithoutHydration(): void
    {
        $rawItems = [
            ['name' => 'X', 'count' => 10],
            ['name' => 'Y', 'count' => 20],
        ];
        $dto = new LazyDto([
            'title' => 'Test',
            'items' => $rawItems,
        ]);

        $arr = $dto->toArray();
        $this->assertCount(2, $arr['items']);
        $this->assertSame('X', $arr['items'][0]['name']);
    }

    public function testClonePreservesLazyData(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Nested', 'count' => 5],
        ]);

        // Clone before accessing lazy field
        $clone = $dto->clone();

        // Both should have the lazy data
        $this->assertTrue($clone->hasNested());

        // Original should still work
        $original = $dto->getNested();
        $this->assertInstanceOf(SimpleDto::class, $original);
        $this->assertSame('Nested', $original->getName());

        // Clone should also hydrate correctly
        $cloneNested = $clone->getNested();
        $this->assertInstanceOf(SimpleDto::class, $cloneNested);
        $this->assertSame('Nested', $cloneNested->getName());

        // They should be different instances
        $this->assertNotSame($original, $cloneNested);
    }

    public function testClonePreservesLazyCollection(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'items' => [
                ['name' => 'A', 'count' => 1],
                ['name' => 'B', 'count' => 2],
            ],
        ]);

        // Clone before accessing lazy collection
        $clone = $dto->clone();

        // Clone should have the lazy data
        $this->assertTrue($clone->hasItems());

        // Access items on clone
        $cloneItems = $clone->getItems();
        $this->assertCount(2, $cloneItems);
        $this->assertSame('A', $cloneItems[0]->getName());

        // Original should also still work
        $originalItems = $dto->getItems();
        $this->assertCount(2, $originalItems);
        $this->assertSame('A', $originalItems[0]->getName());

        // They should be different instances
        $this->assertNotSame($originalItems[0], $cloneItems[0]);
    }

    public function testLazyNullValueIsDetectedCorrectly(): void
    {
        // This tests that array_key_exists is used instead of isset
        // because isset returns false for null values
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => null,
        ]);

        // hasNested should return true because the key exists (even though value is null)
        $this->assertTrue($dto->hasNested());

        // getNested should return null
        $this->assertNull($dto->getNested());

        // After accessing, hasNested should still work
        $this->assertFalse($dto->hasNested());
    }

    public function testTouchedToArrayPreservesLazyData(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Nested', 'count' => 5],
        ]);

        // touchedToArray should include the lazy data even if not hydrated
        $touched = $dto->touchedToArray();

        $this->assertSame('Test', $touched['title']);
        $this->assertIsArray($touched['nested']);
        $this->assertSame('Nested', $touched['nested']['name']);
        $this->assertSame(5, $touched['nested']['count']);
    }

    public function testSerializeUnserializePreservesLazyData(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Serialized', 'count' => 42],
        ]);

        // Serialize without hydrating the lazy field
        $serialized = serialize($dto);
        $unserialized = unserialize($serialized);

        // After unserializing, the lazy data should be accessible
        $this->assertSame('Test', $unserialized->getTitle());
        $nested = $unserialized->getNested();
        $this->assertInstanceOf(SimpleDto::class, $nested);
        $this->assertSame('Serialized', $nested->getName());
        $this->assertSame(42, $nested->getCount());
    }

    public function testToArrayWithTypePreservesLazyData(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Typed', 'count' => 99],
        ]);

        // toArray with a type parameter uses the slow path
        $underscored = $dto->toArray('underscored');

        $this->assertSame('Test', $underscored['title']);
        $this->assertIsArray($underscored['nested']);
        $this->assertSame('Typed', $underscored['nested']['name']);
        $this->assertSame(99, $underscored['nested']['count']);
    }

    public function testToArrayWithFieldsPreservesLazyData(): void
    {
        $dto = new LazyDto([
            'title' => 'Test',
            'nested' => ['name' => 'Filtered', 'count' => 7],
        ]);

        // toArray with specific fields uses the slow path
        $filtered = $dto->toArray(null, ['nested']);

        $this->assertArrayHasKey('nested', $filtered);
        $this->assertArrayNotHasKey('title', $filtered);
        $this->assertIsArray($filtered['nested']);
        $this->assertSame('Filtered', $filtered['nested']['name']);
    }
}
