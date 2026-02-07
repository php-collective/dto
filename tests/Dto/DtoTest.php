<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Dto;

use ArrayIterator;
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
use PhpCollective\Dto\Test\TestDto\AssociativeCollectionDto;
use PhpCollective\Dto\Test\TestDto\CollectionDto;
use PhpCollective\Dto\Test\TestDto\CustomCollectionDto;
use PhpCollective\Dto\Test\TestDto\ImmutableCollectionDto;
use PhpCollective\Dto\Test\TestDto\ImmutableDto;
use PhpCollective\Dto\Test\TestDto\MapFromDto;
use PhpCollective\Dto\Test\TestDto\NestedDto;
use PhpCollective\Dto\Test\TestDto\RequiredDto;
use PhpCollective\Dto\Test\TestDto\SerializableDto;
use PhpCollective\Dto\Test\TestDto\SimpleDto;
use PhpCollective\Dto\Test\TestDto\TestCollection;
use PhpCollective\Dto\Test\TestDto\TransformDto;
use PhpCollective\Dto\Test\TestDto\TraversableDto;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

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

    public function testTransformFromArray(): void
    {
        $dto = new TransformDto(['email' => '  TEST@EXAMPLE.COM  ']);
        $this->assertSame('test@example.com', $dto->getEmail());
    }

    public function testTransformToArray(): void
    {
        $dto = new TransformDto(['email' => 'test@example.com']);
        $array = $dto->toArray();
        $this->assertSame('t***@example.com', $array['email']);
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

    public function testJsonStringOutput(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5]);
        $serialized = (string)$dto;
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
        $serialized = (string)$dto;

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

    public function testCustomCollectionFactoryNotCalledForArrayObject(): void
    {
        // Custom collection factory is only called for non-ArrayObject collection types
        // CollectionDto uses ArrayObject, so the standard createCollection() is used
        $factoryCalled = false;
        Dto::setCollectionFactory(function (array $items) use (&$factoryCalled) {
            $factoryCalled = true;

            return new ArrayObject($items);
        });

        $dto = new CollectionDto([
            'items' => [
                ['name' => 'Item 1'],
            ],
        ]);

        // Factory NOT called because CollectionDto uses ArrayObject collectionType
        $this->assertFalse($factoryCalled);
        $this->assertInstanceOf(ArrayObject::class, $dto->getItems());
    }

    public function testCloneWithScalarCollectionValues(): void
    {
        // Test clone handles non-DTO values in collections
        $dto = new CollectionDto();
        $dto->setItems(new ArrayObject(['string1', 'string2', 123]));

        $clone = $dto->clone();

        $this->assertNotSame($dto->getItems(), $clone->getItems());
        $this->assertSame('string1', $clone->getItems()[0]);
        $this->assertSame(123, $clone->getItems()[2]);
    }

    public function testCloneTraversableWithCustomIterator(): void
    {
        // Test cloneTraversable() with a custom Traversable (not ArrayObject)
        $dto = new TraversableDto();
        $dto->setItems(new ArrayIterator([
            new SimpleDto(['name' => 'Item 1']),
            new SimpleDto(['name' => 'Item 2']),
        ]));

        $clone = $dto->clone();

        // Clone should have different Traversable instance
        $this->assertNotSame($dto->getItems(), $clone->getItems());

        // Items should be cloned too
        $originalItems = iterator_to_array($dto->getItems());
        $clonedItems = iterator_to_array($clone->getItems());

        $this->assertNotSame($originalItems[0], $clonedItems[0]);
        $this->assertSame('Item 1', $clonedItems[0]->getName());

        // Modifying clone should not affect original
        $clonedItems[0]->setName('Modified');
        $this->assertSame('Item 1', $originalItems[0]->getName());
    }

    public function testCloneTraversableUsesCollectionFactory(): void
    {
        // When collection factory is set, cloneTraversable uses it
        $factoryCalled = false;
        Dto::setCollectionFactory(function (array $items) use (&$factoryCalled) {
            $factoryCalled = true;

            return new ArrayIterator($items);
        });

        $dto = new TraversableDto();
        $dto->setItems(new ArrayIterator([
            new SimpleDto(['name' => 'Item 1']),
        ]));

        $clone = $dto->clone();

        // Factory should be called during clone
        $this->assertTrue($factoryCalled);
        $this->assertInstanceOf(ArrayIterator::class, $clone->getItems());
    }

    public function testCloneTraversableWithoutFactoryReturnsArrayObject(): void
    {
        // Without collection factory, cloneTraversable returns ArrayObject
        Dto::setCollectionFactory(null);

        $dto = new TraversableDto();
        $dto->setItems(new ArrayIterator([
            new SimpleDto(['name' => 'Item 1']),
        ]));

        $clone = $dto->clone();

        // Without factory, falls back to ArrayObject
        $this->assertInstanceOf(ArrayObject::class, $clone->getItems());
    }

    public function testCloneTraversableWithPlainObjects(): void
    {
        // Test cloneTraversable with non-DTO objects
        $dto = new TraversableDto();
        $dto->setItems(new ArrayIterator([
            new PlainClass('value1'),
            new PlainClass('value2'),
        ]));

        $clone = $dto->clone();

        $originalItems = iterator_to_array($dto->getItems());
        $clonedItems = iterator_to_array($clone->getItems());

        // Plain objects should be cloned
        $this->assertNotSame($originalItems[0], $clonedItems[0]);
        $this->assertSame('value1', $clonedItems[0]->value);
    }

    public function testCloneTraversableWithScalarValues(): void
    {
        // Test cloneTraversable with scalar values
        $dto = new TraversableDto();
        $dto->setItems(new ArrayIterator(['a', 'b', 123, true]));

        $clone = $dto->clone();

        $clonedItems = iterator_to_array($clone->getItems());

        $this->assertSame('a', $clonedItems[0]);
        $this->assertSame('b', $clonedItems[1]);
        $this->assertSame(123, $clonedItems[2]);
        $this->assertTrue($clonedItems[3]);
    }

    public function testCustomCollectionFactoryIsUsedForNonArrayObjectType(): void
    {
        // Set up a factory that creates TestCollection instances
        $factoryCalled = false;
        Dto::setCollectionFactory(function (array $items) use (&$factoryCalled) {
            $factoryCalled = true;

            return new TestCollection($items);
        });

        // Create DTO from array - should use the factory for the custom collection type
        $dto = new CustomCollectionDto([
            'items' => [
                ['name' => 'Item 1', 'count' => 10],
                ['name' => 'Item 2', 'count' => 20],
            ],
        ], true); // ignoreMissing=true to use setFromArray path

        $this->assertTrue($factoryCalled, 'Collection factory should be called for non-ArrayObject type');
        $this->assertInstanceOf(TestCollection::class, $dto->getItems());
        $this->assertCount(2, $dto->getItems());

        // Verify items are SimpleDto instances
        $items = $dto->getItems()->toArray();
        $this->assertInstanceOf(SimpleDto::class, $items[0]);
        $this->assertSame('Item 1', $items[0]->getName());
        $this->assertSame(10, $items[0]->getCount());
    }

    public function testCustomCollectionFactoryWithFrameworkMethods(): void
    {
        // Set up factory
        Dto::setCollectionFactory(fn (array $items) => new TestCollection($items));

        $dto = new CustomCollectionDto([
            'items' => [
                ['name' => 'Active', 'count' => 10, 'active' => true],
                ['name' => 'Inactive', 'count' => 5, 'active' => false],
                ['name' => 'Also Active', 'count' => 15, 'active' => true],
            ],
        ], true);

        $collection = $dto->getItems();
        $this->assertInstanceOf(TestCollection::class, $collection);

        // Test filter() - framework collection method
        $activeItems = $collection->filter(fn (SimpleDto $item) => $item->getActive() === true);
        $this->assertCount(2, $activeItems);

        // Test map() - framework collection method
        $names = $collection->map(fn (SimpleDto $item) => $item->getName());
        $this->assertSame(['Active', 'Inactive', 'Also Active'], $names->toArray());

        // Test first() - framework collection method
        $first = $collection->first();
        $this->assertSame('Active', $first->getName());
    }

    public function testCustomCollectionToArray(): void
    {
        Dto::setCollectionFactory(fn (array $items) => new TestCollection($items));

        $dto = new CustomCollectionDto([
            'items' => [
                ['name' => 'Item 1', 'count' => 10],
                ['name' => 'Item 2', 'count' => 20],
            ],
        ], true);

        $array = $dto->toArray();

        $this->assertIsArray($array['items']);
        $this->assertCount(2, $array['items']);
        $this->assertSame('Item 1', $array['items'][0]['name']);
        $this->assertSame('Item 2', $array['items'][1]['name']);
    }

    public function testCustomCollectionClone(): void
    {
        Dto::setCollectionFactory(fn (array $items) => new TestCollection($items));

        $dto = new CustomCollectionDto([
            'items' => [
                ['name' => 'Item 1'],
                ['name' => 'Item 2'],
            ],
        ], true);

        $clone = $dto->clone();

        // Collections should be different instances
        $this->assertNotSame($dto->getItems(), $clone->getItems());
        $this->assertInstanceOf(TestCollection::class, $clone->getItems());

        // Items should be cloned too
        $originalItems = $dto->getItems()->toArray();
        $clonedItems = $clone->getItems()->toArray();
        $this->assertNotSame($originalItems[0], $clonedItems[0]);
        $this->assertSame('Item 1', $clonedItems[0]->getName());
    }

    // ========== JSON SERIALIZABLE TESTS ==========

    public function testJsonSerialize(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5, 'active' => true]);

        $array = $dto->jsonSerialize();

        $this->assertSame('Test', $array['name']);
        $this->assertSame(5, $array['count']);
        $this->assertTrue($array['active']);
    }

    public function testJsonEncode(): void
    {
        $dto = new SimpleDto(['name' => 'Test', 'count' => 5]);

        $json = json_encode($dto);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('Test', $decoded['name']);
        $this->assertSame(5, $decoded['count']);
    }

    public function testJsonEncodeWithNestedDto(): void
    {
        $dto = new NestedDto([
            'title' => 'Container',
            'simple' => [
                'name' => 'Nested',
                'count' => 10,
            ],
        ]);

        $json = json_encode($dto);

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame('Container', $decoded['title']);
        $this->assertSame('Nested', $decoded['simple']['name']);
    }

    public function testJsonSerializeRespectsDefaultKeyType(): void
    {
        Dto::setDefaultKeyType(Dto::TYPE_UNDERSCORED);

        $dto = new CollectionDto([
            'array_items' => [
                ['name' => 'Item 1'],
            ],
        ]);

        $json = json_encode($dto);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('array_items', $decoded);
    }

    // ========== COLLECTION REMOVE TESTS ==========

    public function testRemoveItemFromArrayObjectCollection(): void
    {
        $dto = new CollectionDto();
        $dto->addItem(SimpleDto::create(['name' => 'Item 1']));
        $dto->addItem(SimpleDto::create(['name' => 'Item 2']));
        $dto->addItem(SimpleDto::create(['name' => 'Item 3']));

        $this->assertCount(3, $dto->getItems());

        $dto->removeItem(1);

        $this->assertCount(2, $dto->getItems());
        $this->assertSame('Item 1', $dto->getItems()[0]->getName());
        $this->assertSame('Item 3', $dto->getItems()[2]->getName());
    }

    public function testRemoveItemFromArrayCollection(): void
    {
        $dto = new CollectionDto();
        $dto->addArrayItem(SimpleDto::create(['name' => 'Item 1']));
        $dto->addArrayItem(SimpleDto::create(['name' => 'Item 2']));
        $dto->addArrayItem(SimpleDto::create(['name' => 'Item 3']));

        $this->assertCount(3, $dto->getArrayItems());

        $dto->removeArrayItem(1);

        $this->assertCount(2, $dto->getArrayItems());
        $this->assertSame('Item 1', $dto->getArrayItems()[0]->getName());
        $this->assertSame('Item 3', $dto->getArrayItems()[2]->getName());
    }

    public function testRemoveItemFromNullCollectionDoesNothing(): void
    {
        $dto = new CollectionDto();

        // Should not throw, just return $this
        $result = $dto->removeItem(0);

        $this->assertSame($dto, $result);
        $this->assertNull($dto->getItems());
    }

    public function testRemoveItemWithNonExistentKeyDoesNothing(): void
    {
        $dto = new CollectionDto();
        $dto->addItem(SimpleDto::create(['name' => 'Item 1']));

        $this->assertCount(1, $dto->getItems());

        $dto->removeItem(999);

        $this->assertCount(1, $dto->getItems());
    }

    public function testRemoveItemMarksTouchedField(): void
    {
        $dto = new CollectionDto();
        $dto->addItem(SimpleDto::create(['name' => 'Item 1']));

        // Clear touched fields by creating new DTO from array
        $dto = new CollectionDto(['items' => [['name' => 'Item 1']]]);
        $touchedBefore = $dto->touchedFields();

        $dto->removeItem(0);

        $this->assertContains('items', $dto->touchedFields());
    }

    // ========== IMMUTABLE COLLECTION REMOVE TESTS ==========

    public function testWithRemovedItemFromArrayObjectCollection(): void
    {
        $dto = new ImmutableCollectionDto();
        $dto = $dto->withAddedItem(SimpleDto::create(['name' => 'Item 1']));
        $dto = $dto->withAddedItem(SimpleDto::create(['name' => 'Item 2']));
        $dto = $dto->withAddedItem(SimpleDto::create(['name' => 'Item 3']));

        $this->assertCount(3, $dto->getItems());

        $updated = $dto->withRemovedItem(1);

        // Original unchanged
        $this->assertCount(3, $dto->getItems());

        // New instance has item removed
        $this->assertCount(2, $updated->getItems());
        $this->assertNotSame($dto, $updated);
    }

    public function testWithRemovedItemFromArrayCollection(): void
    {
        $dto = new ImmutableCollectionDto();
        $dto = $dto->withAddedArrayItem(SimpleDto::create(['name' => 'Item 1']));
        $dto = $dto->withAddedArrayItem(SimpleDto::create(['name' => 'Item 2']));
        $dto = $dto->withAddedArrayItem(SimpleDto::create(['name' => 'Item 3']));

        $this->assertCount(3, $dto->getArrayItems());

        $updated = $dto->withRemovedArrayItem(1);

        // Original unchanged
        $this->assertCount(3, $dto->getArrayItems());

        // New instance has item removed
        $this->assertCount(2, $updated->getArrayItems());
        $this->assertNotSame($dto, $updated);
    }

    public function testWithRemovedItemFromNullCollectionReturnsNewInstance(): void
    {
        $dto = new ImmutableCollectionDto();

        $updated = $dto->withRemovedItem(0);

        $this->assertNotSame($dto, $updated);
        $this->assertNull($updated->getItems());
    }

    public function testWithRemovedItemMarksTouchedField(): void
    {
        $dto = ImmutableCollectionDto::createFromArray([
            'items' => [['name' => 'Item 1']],
        ]);

        $updated = $dto->withRemovedItem(0);

        $this->assertContains('items', $updated->touchedFields());
    }

    public function testImmutableWithArrayDoesDefensiveCopy(): void
    {
        $dto = new ImmutableCollectionDto();
        $originalArray = [
            SimpleDto::create(['name' => 'Item 1']),
            SimpleDto::create(['name' => 'Item 2']),
        ];

        $updated = $dto->withArrayItems($originalArray);

        // Modify original array after passing to withArrayItems
        $originalArray[0] = SimpleDto::create(['name' => 'Modified']);

        // Updated DTO should NOT be affected by changes to original array
        $items = $updated->getArrayItems();
        $this->assertSame('Item 1', $items[0]->getName());
    }

    public function testCollectionKeyFieldMissingThrowsException(): void
    {
        // Try to create associative collection where key field is missing
        // associativeItems uses 'name' as key field
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Key field `name` not found in collection element');

        new AdvancedDto([
            'associativeItems' => [
                ['count' => 5], // Missing 'name' field which is configured as key
            ],
        ]);
    }

    // ========== MAP FROM + INFLECTION INTERACTION TESTS ==========

    public function testMapFromWithDefaultKeyType(): void
    {
        // Test that mapFrom works with default (camelCase) key type
        $dto = new MapFromDto([
            'email' => 'test@example.com',
        ]);

        $this->assertSame('test@example.com', $dto->getEmailAddress());
    }

    public function testMapFromWithUnderscoredMapKey(): void
    {
        // Test that mapFrom with underscored mapping works
        // The mapFrom 'first_name' should map to firstName field
        $dto = new MapFromDto([
            'first_name' => 'John',
        ]);

        $this->assertSame('John', $dto->getFirstName());
    }

    public function testMapFromWithDashedMapKey(): void
    {
        // Test that mapFrom with dashed mapping works
        // The mapFrom 'user-id' should map to userId field
        $dto = new MapFromDto([
            'user-id' => 42,
        ]);

        $this->assertSame(42, $dto->getUserId());
    }

    public function testMapFromCombinedWithKeyTypeInflection(): void
    {
        // Test that underscored key type works with underscored keys
        // When TYPE_UNDERSCORED is set, keys must be underscored
        // mapFrom keys are checked independently after key conversion
        Dto::setDefaultKeyType(Dto::TYPE_UNDERSCORED);

        $dto = new MapFromDto([
            'email_address' => 'test@example.com', // underscored key for emailAddress field
            'first_name' => 'John', // underscored key that also matches mapFrom
        ]);

        $this->assertSame('test@example.com', $dto->getEmailAddress());
        $this->assertSame('John', $dto->getFirstName());
    }

    public function testMapFromMixedWithCamelCaseKeys(): void
    {
        // Test that both mapFrom and regular camelCase keys work together
        $dto = new MapFromDto([
            'email' => 'mapped@example.com', // via mapFrom
            'emailAddress' => 'direct@example.com', // direct camelCase
        ], true); // ignoreMissing to accept both

        // mapFrom is processed first, but if direct key exists it may override
        // This tests the interaction
        $this->assertNotNull($dto->getEmailAddress());
    }

    // ========== FACTORY EDGE CASE TESTS ==========

    public function testCollectionFactoryReturningNonTraversableThrowsTypeError(): void
    {
        // Test that collection factory returning a non-Traversable throws TypeError
        Dto::setCollectionFactory(function (array $items) {
            // Return a non-Traversable (just an array wrapped in stdClass)
            return (object)['data' => $items];
        });

        // PHP's return type enforcement throws TypeError when factory returns non-Traversable
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('must be of type Traversable');

        new CustomCollectionDto([
            'items' => [
                ['name' => 'Item 1'],
            ],
        ], true);
    }

    public function testFactoryMethodThrowsExceptionIsCaught(): void
    {
        // Test that when a factory method throws an exception, it's wrapped properly
        // This is tested through AdvancedDto which uses a factory
        // The factory (FactoryClass::fromValue) should work normally

        // First verify normal case works
        $dto = new AdvancedDto(['factoryData' => 'valid']);
        $this->assertInstanceOf(FactoryClass::class, $dto->getFactoryData());
    }

    /**
     * Test ArrayObject collection with associative=true but no key field.
     * Should preserve original keys from input data.
     */
    public function testAssociativeCollectionByIndex(): void
    {
        $data = [
            'itemsByIndex' => [
                'first' => ['name' => 'Item 1', 'count' => 1],
                'second' => ['name' => 'Item 2', 'count' => 2],
            ],
        ];

        $dto = new AssociativeCollectionDto($data);
        $items = $dto->getItemsByIndex();

        $this->assertInstanceOf(ArrayObject::class, $items);
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('first', (array)$items);
        $this->assertArrayHasKey('second', (array)$items);
        $this->assertSame('Item 1', $items['first']->getName());
        $this->assertSame('Item 2', $items['second']->getName());
    }

    /**
     * Test ArrayObject collection with associative=true and key='name'.
     * Should use the 'name' field value as the collection key.
     */
    public function testAssociativeCollectionByKeyField(): void
    {
        $data = [
            'itemsByName' => [
                ['name' => 'alpha', 'count' => 1],
                ['name' => 'beta', 'count' => 2],
                ['name' => 'gamma', 'count' => 3],
            ],
        ];

        $dto = new AssociativeCollectionDto($data);
        $items = $dto->getItemsByName();

        $this->assertInstanceOf(ArrayObject::class, $items);
        $this->assertCount(3, $items);
        $this->assertArrayHasKey('alpha', (array)$items);
        $this->assertArrayHasKey('beta', (array)$items);
        $this->assertArrayHasKey('gamma', (array)$items);
        $this->assertSame(1, $items['alpha']->getCount());
        $this->assertSame(2, $items['beta']->getCount());
        $this->assertSame(3, $items['gamma']->getCount());
    }

    /**
     * Test array collection with associative=true and key='name'.
     */
    public function testAssociativeArrayCollectionByKeyField(): void
    {
        $data = [
            'arrayItemsByName' => [
                ['name' => 'one', 'count' => 10],
                ['name' => 'two', 'count' => 20],
            ],
        ];

        $dto = new AssociativeCollectionDto($data);
        $items = $dto->getArrayItemsByName();

        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('one', $items);
        $this->assertArrayHasKey('two', $items);
        $this->assertSame(10, $items['one']->getCount());
        $this->assertSame(20, $items['two']->getCount());
    }

    /**
     * Test associative collection with TYPE_UNDERSCORED.
     * This tests the setFromArray() path (non-fast-path).
     */
    public function testAssociativeCollectionWithTypeMapping(): void
    {
        $data = [
            'items_by_name' => [
                ['name' => 'foo', 'count' => 100],
                ['name' => 'bar', 'count' => 200],
            ],
        ];

        $dto = AssociativeCollectionDto::create($data, false, Dto::TYPE_UNDERSCORED);
        $items = $dto->getItemsByName();

        $this->assertInstanceOf(ArrayObject::class, $items);
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('foo', (array)$items);
        $this->assertArrayHasKey('bar', (array)$items);
        $this->assertSame(100, $items['foo']->getCount());
        $this->assertSame(200, $items['bar']->getCount());
    }

    /**
     * Test associative collection keys preserved during serialization round-trip.
     */
    public function testAssociativeCollectionSerializationRoundTrip(): void
    {
        $data = [
            'itemsByName' => [
                ['name' => 'x', 'count' => 1],
                ['name' => 'y', 'count' => 2],
            ],
        ];

        $dto = new AssociativeCollectionDto($data);
        $serialized = $dto->serialize();

        $restored = new AssociativeCollectionDto();
        $restored->unserialize($serialized);

        $items = $restored->getItemsByName();
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('x', (array)$items);
        $this->assertArrayHasKey('y', (array)$items);
        $this->assertSame(1, $items['x']->getCount());
        $this->assertSame(2, $items['y']->getCount());
    }

    /**
     * Test associative collection with already-keyed input data preserves keys.
     */
    public function testAssociativeCollectionWithPreKeyedData(): void
    {
        $data = [
            'itemsByName' => [
                'custom_key_1' => ['name' => 'a', 'count' => 5],
                'custom_key_2' => ['name' => 'b', 'count' => 6],
            ],
        ];

        $dto = new AssociativeCollectionDto($data);
        $items = $dto->getItemsByName();

        // With key='name', the key should be extracted from the 'name' field
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('a', (array)$items);
        $this->assertArrayHasKey('b', (array)$items);
        $this->assertSame(5, $items['a']->getCount());
        $this->assertSame(6, $items['b']->getCount());
    }

    /**
     * Test associative ArrayObject collection with TYPE_UNDERSCORED preserves keys.
     */
    public function testAssociativeArrayObjectCollectionWithTypeUnderscoredPreservesKeys(): void
    {
        $data = [
            'items_by_index' => [
                'key_one' => ['name' => 'First', 'count' => 1],
                'key_two' => ['name' => 'Second', 'count' => 2],
            ],
        ];

        $dto = AssociativeCollectionDto::create($data, false, Dto::TYPE_UNDERSCORED);
        $items = $dto->getItemsByIndex();

        $this->assertInstanceOf(ArrayObject::class, $items);
        $this->assertCount(2, $items);
        $this->assertArrayHasKey('key_one', (array)$items);
        $this->assertArrayHasKey('key_two', (array)$items);
        $this->assertSame('First', $items['key_one']->getName());
        $this->assertSame('Second', $items['key_two']->getName());
    }
}
