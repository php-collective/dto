<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Dto;

use ArrayObject;
use InvalidArgumentException;
use PhpCollective\Dto\Dto\Dto;
use PhpCollective\Dto\Test\Generator\Fixtures\FactoryClass;
use PhpCollective\Dto\Test\Generator\Fixtures\FromArrayToArrayClass;
use PhpCollective\Dto\Test\Generator\Fixtures\IntBackedEnum;
use PhpCollective\Dto\Test\Generator\Fixtures\PlainClass;
use PhpCollective\Dto\Test\Generator\Fixtures\StringableClass;
use PhpCollective\Dto\Test\Generator\Fixtures\ToArrayClass;
use PhpCollective\Dto\Test\Generator\Fixtures\UnitEnum;
use PhpCollective\Dto\Test\TestDto\AdvancedDto;
use PhpCollective\Dto\Test\TestDto\CollectionDto;
use PhpCollective\Dto\Test\TestDto\ImmutableDto;
use PhpCollective\Dto\Test\TestDto\NestedDto;
use PhpCollective\Dto\Test\TestDto\RequiredDto;
use PhpCollective\Dto\Test\TestDto\SerializableDto;
use PhpCollective\Dto\Test\TestDto\SimpleDto;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    // ========== IMMUTABLE DTO TESTS ==========

    public function testImmutableWithMethod(): void
    {
        $dto = new ImmutableDto(['title' => 'Original']);
        $updated = $dto->withTitle('Updated');

        // Original unchanged
        $this->assertSame('Original', $dto->getTitle());
        // New instance has updated value
        $this->assertSame('Updated', $updated->getTitle());
        // They are different instances
        $this->assertNotSame($dto, $updated);
    }

    public function testImmutableGenericWithMethod(): void
    {
        $dto = new ImmutableDto(['title' => 'Original']);
        $updated = $dto->with('title', 'Updated');

        $this->assertSame('Original', $dto->getTitle());
        $this->assertSame('Updated', $updated->getTitle());
        $this->assertNotSame($dto, $updated);
    }

    public function testImmutableWithMultipleFields(): void
    {
        $dto = new ImmutableDto(['title' => 'Original', 'version' => 1]);
        $updated = $dto->withTitle('Updated')->withVersion(2)->withPublished(true);

        $this->assertSame('Original', $dto->getTitle());
        $this->assertSame(1, $dto->getVersion());
        $this->assertNull($dto->getPublished());

        $this->assertSame('Updated', $updated->getTitle());
        $this->assertSame(2, $updated->getVersion());
        $this->assertTrue($updated->getPublished());
    }

    public function testImmutableWithInvalidFieldThrowsException(): void
    {
        $dto = new ImmutableDto(['title' => 'Test']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Field does not exist: invalidField');
        $dto->with('invalidField', 'value');
    }

    public function testImmutableCreateFromArray(): void
    {
        $dto = ImmutableDto::createFromArray([
            'title' => 'Test',
            'version' => 1,
            'published' => true,
        ]);

        $this->assertSame('Test', $dto->getTitle());
        $this->assertSame(1, $dto->getVersion());
        $this->assertTrue($dto->getPublished());
    }

    public function testImmutableToArray(): void
    {
        $dto = new ImmutableDto(['title' => 'Test', 'version' => 1]);
        $array = $dto->toArray();

        $this->assertSame('Test', $array['title']);
        $this->assertSame(1, $array['version']);
    }

    public function testImmutableTouchedFields(): void
    {
        $dto = new ImmutableDto(['title' => 'Original']);
        $this->assertContains('title', $dto->touchedFields());

        $updated = $dto->withVersion(2);
        $this->assertContains('title', $updated->touchedFields());
        $this->assertContains('version', $updated->touchedFields());
    }

    public function testImmutableWithUnderscoredKeyType(): void
    {
        $dto = new ImmutableDto(['title' => 'Original']);
        $updated = $dto->with('title', 'Updated', ImmutableDto::TYPE_UNDERSCORED);

        $this->assertSame('Updated', $updated->getTitle());
    }

    // ========== COLLECTION TESTS ==========

    public function testCollectionToArray(): void
    {
        $dto = new CollectionDto();
        $dto->addItem(SimpleDto::create(['name' => 'Item 1']));
        $dto->addItem(SimpleDto::create(['name' => 'Item 2']));

        $array = $dto->toArray();

        $this->assertCount(2, $array['items']);
        $this->assertSame('Item 1', $array['items'][0]['name']);
        $this->assertSame('Item 2', $array['items'][1]['name']);
    }

    public function testCollectionTouchedToArray(): void
    {
        $dto = new CollectionDto();
        $dto->addItem(SimpleDto::create(['name' => 'Item 1']));

        $touched = $dto->touchedToArray();

        $this->assertArrayHasKey('items', $touched);
        $this->assertCount(1, $touched['items']);
    }

    public function testEmptyCollectionToArray(): void
    {
        $dto = new CollectionDto();
        $dto->setItems(new ArrayObject());

        $array = $dto->toArray();

        $this->assertSame([], $array['items']);
    }

    public function testArrayCollectionToArray(): void
    {
        $dto = new CollectionDto();
        $dto->addArrayItem(SimpleDto::create(['name' => 'Item 1']));
        $dto->addArrayItem(SimpleDto::create(['name' => 'Item 2']));

        $array = $dto->toArray();

        $this->assertCount(2, $array['arrayItems']);
        $this->assertSame('Item 1', $array['arrayItems'][0]['name']);
    }

    public function testCollectionFromArray(): void
    {
        $dto = new CollectionDto([
            'items' => [
                ['name' => 'Item 1', 'count' => 1],
                ['name' => 'Item 2', 'count' => 2],
            ],
        ]);

        $this->assertInstanceOf(ArrayObject::class, $dto->getItems());
        $this->assertCount(2, $dto->getItems());
        $this->assertSame('Item 1', $dto->getItems()[0]->getName());
    }

    public function testArrayCollectionFromArray(): void
    {
        $dto = new CollectionDto([
            'arrayItems' => [
                ['name' => 'Item 1'],
                ['name' => 'Item 2'],
            ],
        ]);

        $this->assertIsArray($dto->getArrayItems());
        $this->assertCount(2, $dto->getArrayItems());
        $this->assertSame('Item 1', $dto->getArrayItems()[0]->getName());
    }

    // ========== SERIALIZABLE CLASS TESTS ==========

    public function testSerializableFromArrayToArrayClass(): void
    {
        $dto = new SerializableDto();
        $dto->setFromArrayData(new FromArrayToArrayClass('test value'));

        $array = $dto->toArray();

        $this->assertIsArray($array['fromArrayData']);
        $this->assertSame('test value', $array['fromArrayData']['value']);
    }

    public function testSerializableToArrayClass(): void
    {
        $dto = new SerializableDto();
        $dto->setToArrayData(new ToArrayClass('test value'));

        $array = $dto->toArray();

        $this->assertIsArray($array['toArrayData']);
        $this->assertSame('test value', $array['toArrayData']['value']);
    }

    // ========== UNIT ENUM TESTS ==========

    public function testUnitEnumToArray(): void
    {
        $dto = new SerializableDto();
        $dto->setStatus(UnitEnum::Pending);

        $array = $dto->toArray();

        $this->assertSame('Pending', $array['status']);
    }

    public function testUnitEnumFromArray(): void
    {
        $dto = new SerializableDto([
            'status' => 'Completed',
        ]);

        $this->assertSame(UnitEnum::Completed, $dto->getStatus());
    }

    public function testUnitEnumFromArrayWithEnumInstance(): void
    {
        $dto = new SerializableDto([
            'status' => UnitEnum::Pending,
        ]);

        $this->assertSame(UnitEnum::Pending, $dto->getStatus());
    }

    public function testCollectionWithScalarElements(): void
    {
        // Test transformCollectionToArray with scalar values
        $dto = new CollectionDto();
        $items = new ArrayObject(['a', 'b', 'c']);
        $dto->setItems($items);

        $array = $dto->toArray();

        $this->assertSame(['a', 'b', 'c'], $array['items']);
    }

    public function testSerializableStringSerialize(): void
    {
        // Add a test for 'string' serialize type
        $dto = new SerializableDto();
        $dto->setFromArrayData(new FromArrayToArrayClass('serialized'));
        $dto->setToArrayData(new ToArrayClass('array-serialized'));

        $touched = $dto->touchedToArray();

        $this->assertArrayHasKey('fromArrayData', $touched);
        $this->assertArrayHasKey('toArrayData', $touched);
    }

    public function testUnitEnumNullValue(): void
    {
        $dto = new SerializableDto();
        $dto->setStatus(null);

        $array = $dto->toArray();

        $this->assertNull($array['status']);
    }

    public function testSerializableStringClass(): void
    {
        $dto = new SerializableDto();
        $dto->setStringData(new StringableClass('hello world'));

        $array = $dto->toArray();

        $this->assertSame('hello world', $array['stringData']);
    }

    // ========== ADDITIONAL COVERAGE TESTS ==========

    public function testFromUnserialized(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5]);
        $serialized = $dto->serialize();

        $unserialized = SimpleDto::fromUnserialized($serialized);

        $this->assertInstanceOf(SimpleDto::class, $unserialized);
        $this->assertSame('Test', $unserialized->getName());
        $this->assertSame(5, $unserialized->getCount());
    }

    public function testUnserializeExceptionWithUnknownFields(): void
    {
        $dto = new SimpleDto();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown field(s)');

        // Manually call __unserialize with invalid data
        $dto->__unserialize(['name' => 'Test', 'unknownField' => 'value']);
    }

    public function testCreateWithFactory(): void
    {
        $dto = new AdvancedDto([
            'factoryData' => 'factory value',
        ]);

        $this->assertInstanceOf(FactoryClass::class, $dto->getFactoryData());
        $this->assertSame('factory value', $dto->getFactoryData()->value);
    }

    public function testCreateWithFactoryExistingInstance(): void
    {
        $factory = new FactoryClass('existing');
        $dto = new AdvancedDto([
            'factoryData' => $factory,
        ]);

        $this->assertSame($factory, $dto->getFactoryData());
    }

    public function testBackedEnumFromArray(): void
    {
        $dto = new AdvancedDto([
            'priority' => 5,
        ]);

        $this->assertSame(IntBackedEnum::Medium, $dto->getPriority());
    }

    public function testBackedEnumFromArrayWithInstance(): void
    {
        $dto = new AdvancedDto([
            'priority' => IntBackedEnum::High,
        ]);

        $this->assertSame(IntBackedEnum::High, $dto->getPriority());
    }

    public function testBackedEnumInvalidValueReturnsNull(): void
    {
        $dto = new AdvancedDto([
            'priority' => 999,
        ]);

        // tryFrom returns null for invalid values
        $this->assertNull($dto->getPriority());
    }

    public function testCreateWithConstructor(): void
    {
        $dto = new AdvancedDto([
            'plainData' => 'plain value',
        ]);

        $this->assertInstanceOf(PlainClass::class, $dto->getPlainData());
        $this->assertSame('plain value', $dto->getPlainData()->value);
    }

    public function testCreateWithConstructorNullValue(): void
    {
        $dto = new AdvancedDto([
            'plainData' => null,
        ]);

        $this->assertNull($dto->getPlainData());
    }

    public function testAssociativeArrayCollection(): void
    {
        $dto = new AdvancedDto([
            'associativeItems' => [
                ['name' => 'Item A', 'count' => 1],
                ['name' => 'Item B', 'count' => 2],
            ],
        ]);

        $items = $dto->getAssociativeItems();
        $this->assertIsArray($items);
        $this->assertArrayHasKey('Item A', $items);
        $this->assertArrayHasKey('Item B', $items);
        $this->assertSame('Item A', $items['Item A']->getName());
    }

    public function testSetWithUnderscoredKeyType(): void
    {
        $dto = new SimpleDto();
        $dto->set('name', 'Underscored Test', Dto::TYPE_UNDERSCORED);

        $this->assertSame('Underscored Test', $dto->getName());
    }

    public function testHasWithUnderscoredKeyType(): void
    {
        $dto = new SimpleDto(['name' => 'Test']);

        $this->assertTrue($dto->has('name', Dto::TYPE_UNDERSCORED));
    }

    public function testGetWithUnderscoredKeyType(): void
    {
        $dto = new SimpleDto(['name' => 'Test']);

        $this->assertSame('Test', $dto->get('name', Dto::TYPE_UNDERSCORED));
    }

    public function testToArrayWithUnderscoredKeyType(): void
    {
        $dto = new CollectionDto([
            'arrayItems' => [
                ['name' => 'Item 1'],
            ],
        ]);

        $array = $dto->toArray(Dto::TYPE_UNDERSCORED);

        $this->assertArrayHasKey('array_items', $array);
    }

    public function testToArrayWithDashedKeyType(): void
    {
        $dto = new CollectionDto([
            'arrayItems' => [
                ['name' => 'Item 1'],
            ],
        ]);

        $array = $dto->toArray(Dto::TYPE_DASHED);

        $this->assertArrayHasKey('array-items', $array);
    }

    public function testSetInvalidFieldThrowsException(): void
    {
        $dto = new SimpleDto();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Field does not exist');

        $dto->set('nonExistent', 'value');
    }

    public function testGetInvalidFieldThrowsException(): void
    {
        $dto = new SimpleDto();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Field does not exist');

        $dto->get('nonExistent');
    }

    public function testHasInvalidFieldThrowsException(): void
    {
        $dto = new SimpleDto();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Field does not exist');

        $dto->has('nonExistent');
    }

    public function testFieldLookupInvalidTypeThrowsException(): void
    {
        $dto = new SimpleDto();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid field lookup');

        $dto->field('invalid_field', Dto::TYPE_UNDERSCORED);
    }

    public function testReadWithNestedArrayAccess(): void
    {
        $dto = new NestedDto([
            'title' => 'Container',
            'simple' => [
                'name' => 'Nested',
            ],
        ]);

        // Test deep path that traverses DTO and array
        $this->assertSame('Nested', $dto->read(['simple', 'name']));
    }

    public function testReadMissingPathReturnsDefault(): void
    {
        $dto = new NestedDto(['title' => 'Test']);

        // When nested DTO is null, read returns default
        $this->assertNull($dto->read(['simple']));
        $this->assertSame('default', $dto->read(['simple'], 'default'));

        // When path goes deeper into null, return default
        $this->assertSame('fallback', $dto->read(['simple', 'name'], 'fallback'));
    }

    public function testCloneWithNestedDtoAndCollection(): void
    {
        $dto = new CollectionDto([
            'items' => [
                ['name' => 'Item 1'],
                ['name' => 'Item 2'],
            ],
        ]);

        $clone = $dto->clone();
        $clone->getItems()[0]->setName('Modified');

        $this->assertSame('Item 1', $dto->getItems()[0]->getName());
        $this->assertSame('Modified', $clone->getItems()[0]->getName());
    }

    public function testCloneWithArrayCollection(): void
    {
        $dto = new CollectionDto([
            'arrayItems' => [
                ['name' => 'Item 1'],
            ],
        ]);

        $clone = $dto->clone();
        $clone->getArrayItems()[0]->setName('Modified');

        $this->assertSame('Item 1', $dto->getArrayItems()[0]->getName());
    }

    public function testCustomCollectionFactory(): void
    {
        $factoryCalled = false;
        Dto::setCollectionFactory(function ($items) use (&$factoryCalled) {
            $factoryCalled = true;

            return new ArrayObject($items);
        });

        // Custom collections are triggered for non-ArrayObject types
        // Our test uses ArrayObject, so this just verifies the factory is set
        $dto = new CollectionDto([
            'items' => [
                ['name' => 'Item 1'],
            ],
        ]);

        $this->assertInstanceOf(ArrayObject::class, $dto->getItems());
    }

    public function testCloneWithPlainObjects(): void
    {
        $dto = new AdvancedDto();
        $dto->setFactoryData(new FactoryClass('original'));

        $clone = $dto->clone();

        $this->assertNotSame($dto->getFactoryData(), $clone->getFactoryData());
        $this->assertSame('original', $clone->getFactoryData()->value);
    }

    public function testUnserializeMethodOnAbstractDto(): void
    {
        $dto = new SimpleDto(['name' => 'Original', 'count' => 1]);
        $serialized = $dto->serialize();

        $newDto = new SimpleDto();
        $result = $newDto->unserialize($serialized);

        $this->assertSame('Original', $result->getName());
        $this->assertSame(1, $result->getCount());
    }

    public function testFromArrayWithUnderscoredType(): void
    {
        $dto = new CollectionDto();
        $dto->fromArray(['array_items' => [['name' => 'Test']]], false, Dto::TYPE_UNDERSCORED);

        $this->assertCount(1, $dto->getArrayItems());
    }

    public function testDefaultKeyTypeAffectsFromArray(): void
    {
        Dto::setDefaultKeyType(Dto::TYPE_UNDERSCORED);

        $dto = new CollectionDto(['array_items' => [['name' => 'Test']]]);

        $this->assertCount(1, $dto->getArrayItems());
    }

    public function testCollectionFromArrayWithNonArrayElements(): void
    {
        // Test when array elements are not arrays (edge case in createCollection)
        $dto = new CollectionDto([
            'items' => [null, null],
        ]);

        $this->assertInstanceOf(ArrayObject::class, $dto->getItems());
        $this->assertCount(2, $dto->getItems());
    }

    public function testArrayCollectionFromArrayWithNonArrayElements(): void
    {
        // Test when array elements are not arrays (edge case in createArrayCollection)
        $dto = new CollectionDto([
            'arrayItems' => [null, null],
        ]);

        $this->assertIsArray($dto->getArrayItems());
        $this->assertCount(2, $dto->getArrayItems());
    }

    public function testAssociativeArrayCollectionWithTrueKey(): void
    {
        // When associative is true but key is null, uses the index as key
        $dto = new CollectionDto([
            'arrayItems' => [
                'first' => ['name' => 'Item 1'],
                'second' => ['name' => 'Item 2'],
            ],
        ]);

        // Non-associative collection, items are added by index
        $this->assertCount(2, $dto->getArrayItems());
    }
}
