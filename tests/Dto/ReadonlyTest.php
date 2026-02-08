<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Dto;

use Error;
use PhpCollective\Dto\Test\TestDto\ReadonlyDto;
use PHPUnit\Framework\TestCase;

class ReadonlyTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $dto = new ReadonlyDto(['name' => 'Alice', 'age' => 30]);

        $this->assertSame('Alice', $dto->getName());
        $this->assertSame(30, $dto->getAge());
        $this->assertNull($dto->getActive());
    }

    public function testPublicReadAccess(): void
    {
        $dto = new ReadonlyDto(['name' => 'Bob', 'age' => 25, 'active' => true]);

        $this->assertSame('Bob', $dto->name);
        $this->assertSame(25, $dto->age);
        $this->assertTrue($dto->active);
    }

    public function testDirectAssignmentFails(): void
    {
        $dto = new ReadonlyDto(['name' => 'Charlie']);

        $this->expectException(Error::class);
        $dto->name = 'Modified';
    }

    public function testWithMethodReturnsNewInstance(): void
    {
        $original = new ReadonlyDto(['name' => 'Alice', 'age' => 30]);
        $modified = $original->withName('Bob');

        $this->assertSame('Alice', $original->getName());
        $this->assertSame('Bob', $modified->getName());
        $this->assertSame(30, $modified->getAge());
        $this->assertNotSame($original, $modified);
    }

    public function testWithAge(): void
    {
        $dto = new ReadonlyDto(['name' => 'Test', 'age' => 20]);
        $modified = $dto->withAge(25);

        $this->assertSame(20, $dto->getAge());
        $this->assertSame(25, $modified->getAge());
    }

    public function testWithActive(): void
    {
        $dto = new ReadonlyDto(['name' => 'Test']);
        $modified = $dto->withActive(true);

        $this->assertNull($dto->getActive());
        $this->assertTrue($modified->getActive());
    }

    public function testChainedWithMethods(): void
    {
        $dto = new ReadonlyDto(['name' => 'Start']);
        $result = $dto->withName('Changed')->withAge(40)->withActive(false);

        $this->assertSame('Changed', $result->getName());
        $this->assertSame(40, $result->getAge());
        $this->assertFalse($result->getActive());
    }

    public function testToArray(): void
    {
        $dto = new ReadonlyDto(['name' => 'Test', 'age' => 25, 'active' => true]);
        $result = $dto->toArray();

        $this->assertSame([
            'name' => 'Test',
            'age' => 25,
            'active' => true,
        ], $result);
    }

    public function testEmptyConstruction(): void
    {
        $dto = new ReadonlyDto();
        $this->assertNull($dto->getName());
        $this->assertNull($dto->getAge());
        $this->assertNull($dto->getActive());
    }

    public function testHasMethods(): void
    {
        $dto = new ReadonlyDto(['name' => 'Test']);

        $this->assertTrue($dto->hasName());
        $this->assertFalse($dto->hasAge());
        $this->assertFalse($dto->hasActive());
    }

    public function testCreateFromArray(): void
    {
        $dto = ReadonlyDto::createFromArray(['name' => 'Factory', 'age' => 10]);

        $this->assertSame('Factory', $dto->getName());
        $this->assertSame(10, $dto->getAge());
    }

    public function testWithNullValue(): void
    {
        $dto = new ReadonlyDto(['name' => 'Test', 'age' => 25]);
        $modified = $dto->withAge(null);

        $this->assertSame(25, $dto->getAge());
        $this->assertNull($modified->getAge());
    }
}
