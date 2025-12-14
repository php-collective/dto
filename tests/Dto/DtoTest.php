<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Dto;

use ArrayObject;
use InvalidArgumentException;
use PhpCollective\Dto\Dto\Dto;
use PhpCollective\Dto\Test\TestDto\NestedDto;
use PhpCollective\Dto\Test\TestDto\RequiredDto;
use PhpCollective\Dto\Test\TestDto\SimpleDto;
use PHPUnit\Framework\TestCase;

class DtoTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Dto::setDefaultKeyType(null);
        Dto::setCollectionFactory(null);
    }

    public function testCreate(): void
    {
        $dto = SimpleDto::create(['name' => 'Test']);
        $this->assertInstanceOf(SimpleDto::class, $dto);
        $this->assertSame('Test', $dto->getName());
    }

    public function testCreateFromArray(): void
    {
        $dto = SimpleDto::createFromArray(['name' => 'Test', 'count' => 5]);
        $this->assertSame('Test', $dto->getName());
        $this->assertSame(5, $dto->getCount());
    }

    public function testToArray(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5, 'active' => true]);
        $array = $dto->toArray();
        $this->assertSame('Test', $array['name']);
        $this->assertSame(5, $array['count']);
        $this->assertTrue($array['active']);
    }

    public function testTouchedToArray(): void
    {
        $dto = new SimpleDto();
        $dto->setName('Test');
        $touched = $dto->touchedToArray();
        $this->assertArrayHasKey('name', $touched);
        $this->assertArrayNotHasKey('count', $touched);
    }

    public function testFields(): void
    {
        $dto = new SimpleDto();
        $fields = $dto->fields();
        $this->assertContains('name', $fields);
        $this->assertContains('count', $fields);
        $this->assertContains('active', $fields);
    }

    public function testTouchedFields(): void
    {
        $dto = new SimpleDto();
        $this->assertEmpty($dto->touchedFields());
        $dto->setName('Test');
        $this->assertContains('name', $dto->touchedFields());
    }

    public function testHas(): void
    {
        $dto = new SimpleDto();
        $this->assertFalse($dto->has('name'));
        $dto->setName('Test');
        $this->assertTrue($dto->has('name'));
    }

    public function testGet(): void
    {
        $dto = new SimpleDto(['name' => 'Test']);
        $this->assertSame('Test', $dto->get('name'));
    }

    public function testSet(): void
    {
        $dto = new SimpleDto();
        $dto->set('name', 'Test');
        $this->assertSame('Test', $dto->getName());
    }

    public function testFromArray(): void
    {
        $dto = new SimpleDto();
        $dto->fromArray(['name' => 'Updated', 'count' => 10]);
        $this->assertSame('Updated', $dto->getName());
        $this->assertSame(10, $dto->getCount());
    }

    public function testMagicGet(): void
    {
        $dto = new SimpleDto(['name' => 'Test']);
        $this->assertSame('Test', $dto->name);
    }

    public function testMagicSet(): void
    {
        $dto = new SimpleDto();
        $dto->name = 'Test';
        $this->assertSame('Test', $dto->getName());
    }

    public function testMagicIsset(): void
    {
        $dto = new SimpleDto(['name' => 'Test']);
        $this->assertTrue(isset($dto->name));
        $this->assertFalse(isset($dto->count));
    }

    public function testSerialize(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5]);
        $serialized = $dto->serialize();
        $this->assertJson($serialized);
        $decoded = json_decode($serialized, true);
        $this->assertSame('Test', $decoded['name']);
    }

    public function testToString(): void
    {
        $dto = new SimpleDto(['name' => 'Test']);
        $string = (string)$dto;
        $this->assertJson($string);
    }

    public function testClone(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5]);
        $clone = $dto->clone();
        $this->assertSame('Test', $clone->getName());
        $this->assertSame(5, $clone->getCount());
        $clone->setName('Modified');
        $this->assertSame('Test', $dto->getName());
    }

    public function testNestedDto(): void
    {
        $data = [
            'title' => 'Container',
            'simple' => [
                'name' => 'Nested',
                'count' => 10,
            ],
        ];
        $dto = new NestedDto($data);
        $this->assertSame('Container', $dto->getTitle());
        $this->assertInstanceOf(SimpleDto::class, $dto->getSimple());
        $this->assertSame('Nested', $dto->getSimple()->getName());
    }

    public function testNestedDtoToArray(): void
    {
        $dto = new NestedDto([
            'title' => 'Container',
            'simple' => [
                'name' => 'Nested',
            ],
        ]);
        $array = $dto->toArray();
        $this->assertArrayHasKey('simple', $array);
        $this->assertIsArray($array['simple']);
        $this->assertSame('Nested', $array['simple']['name']);
    }

    public function testCloneWithNestedDto(): void
    {
        $dto = new NestedDto([
            'title' => 'Container',
            'simple' => [
                'name' => 'Original',
            ],
        ]);
        $clone = $dto->clone();
        $clone->getSimple()->setName('Modified');
        $this->assertSame('Original', $dto->getSimple()->getName());
    }

    public function testRequiredFieldsValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required fields missing');
        new RequiredDto();
    }

    public function testRequiredFieldsWithData(): void
    {
        $dto = new RequiredDto(['name' => 'Test', 'email' => 'test@example.com']);
        $this->assertSame('Test', $dto->getName());
        $this->assertSame('test@example.com', $dto->getEmail());
    }

    public function testIgnoreMissingFields(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'unknownField' => 'value'], true);
        $this->assertSame('Test', $dto->getName());
    }

    public function testMissingFieldThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing field');
        new SimpleDto(['unknownField' => 'value']);
    }

    public function testDefaultKeyType(): void
    {
        Dto::setDefaultKeyType(Dto::TYPE_UNDERSCORED);
        $this->assertSame(Dto::TYPE_UNDERSCORED, Dto::getDefaultKeyType());
    }

    public function testCollectionFactory(): void
    {
        $items = [];
        Dto::setCollectionFactory(function ($data) use (&$items) {
            $items = $data;

            return new ArrayObject($data);
        });

        $this->assertNotNull(SimpleDto::create());
    }

    public function testDebugInfo(): void
    {
        $dto = new SimpleDto(['name' => 'Test']);
        $debug = $dto->__debugInfo();
        $this->assertArrayHasKey('data', $debug);
        $this->assertArrayHasKey('touched', $debug);
        $this->assertArrayHasKey('extends', $debug);
        $this->assertArrayHasKey('immutable', $debug);
    }

    public function testRead(): void
    {
        $dto = new NestedDto([
            'title' => 'Container',
            'simple' => [
                'name' => 'Nested',
                'count' => 42,
            ],
        ]);

        $this->assertSame('Container', $dto->read(['title']));
        $this->assertSame('Nested', $dto->read(['simple', 'name']));
        $this->assertSame(42, $dto->read(['simple', 'count']));
    }

    public function testReadWithNullField(): void
    {
        $dto = new NestedDto([
            'title' => 'Container',
        ]);

        $this->assertNull($dto->read(['simple']));
        $this->assertSame('default', $dto->read(['simple'], 'default'));
    }

    public function testNativeSerialization(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5]);
        $serialized = serialize($dto);
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(SimpleDto::class, $unserialized);
        $this->assertSame('Test', $unserialized->getName());
        $this->assertSame(5, $unserialized->getCount());
    }
}
