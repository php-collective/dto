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
}
