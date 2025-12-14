<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Config;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpCollective\Dto\Config\Dto;
use PhpCollective\Dto\Config\Field;
use PhpCollective\Dto\Config\Schema;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    public function testFieldString(): void
    {
        $field = Field::string('name');

        $this->assertSame('name', $field->getName());
        $this->assertSame('string', $field->toArray());
    }

    public function testFieldInt(): void
    {
        $field = Field::int('count');

        $this->assertSame('int', $field->toArray());
    }

    public function testFieldFloat(): void
    {
        $field = Field::float('price');

        $this->assertSame('float', $field->toArray());
    }

    public function testFieldBool(): void
    {
        $field = Field::bool('active');

        $this->assertSame('bool', $field->toArray());
    }

    public function testFieldRequired(): void
    {
        $field = Field::string('email')->required();

        $this->assertSame([
            'type' => 'string',
            'required' => true,
        ], $field->toArray());
    }

    public function testFieldDefault(): void
    {
        $field = Field::bool('active')->default(true);

        $this->assertSame([
            'type' => 'bool',
            'defaultValue' => true,
        ], $field->toArray());
    }

    public function testFieldRequiredWithDefault(): void
    {
        $field = Field::string('status')->required()->default('pending');

        $this->assertSame([
            'type' => 'string',
            'required' => true,
            'defaultValue' => 'pending',
        ], $field->toArray());
    }

    public function testFieldArray(): void
    {
        $field = Field::array('tags', 'string');

        $this->assertSame('string[]', $field->toArray());
    }

    public function testFieldDto(): void
    {
        $field = Field::dto('address', 'Address');

        $this->assertSame('Address', $field->toArray());
    }

    public function testFieldCollection(): void
    {
        $field = Field::collection('items', 'Item')->singular('item');

        $this->assertSame([
            'type' => 'Item[]',
            'collection' => true,
            'singular' => 'item',
        ], $field->toArray());
    }

    public function testFieldCollectionAssociative(): void
    {
        $field = Field::collection('items', 'Item')
            ->singular('item')
            ->associative('slug');

        $this->assertSame([
            'type' => 'Item[]',
            'collection' => true,
            'singular' => 'item',
            'associative' => true,
            'key' => 'slug',
        ], $field->toArray());
    }

    public function testFieldClass(): void
    {
        $field = Field::class('createdAt', DateTimeImmutable::class);

        $this->assertSame('\DateTimeImmutable', $field->toArray());
    }

    public function testFieldEnum(): void
    {
        $field = Field::enum('status', 'App\Enum\Status');

        $this->assertSame('\App\Enum\Status', $field->toArray());
    }

    public function testFieldDeprecated(): void
    {
        $field = Field::string('oldField')->deprecated('Use newField instead');

        $this->assertSame([
            'type' => 'string',
            'deprecated' => 'Use newField instead',
        ], $field->toArray());
    }

    public function testFieldUnion(): void
    {
        $field = Field::union('id', 'int', 'string');

        $this->assertSame('id', $field->getName());
        $this->assertSame('int|string', $field->toArray());
    }

    public function testFieldUnionWithThreeTypes(): void
    {
        $field = Field::union('value', 'int', 'float', 'string');

        $this->assertSame('int|float|string', $field->toArray());
    }

    public function testFieldUnionRequired(): void
    {
        $field = Field::union('id', 'int', 'string')->required();

        $this->assertSame([
            'type' => 'int|string',
            'required' => true,
        ], $field->toArray());
    }

    public function testFieldUnionRequiresAtLeastTwoTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Union types require at least 2 types');
        Field::union('value', 'int');
    }

    public function testFieldFactory(): void
    {
        $field = Field::class('money', 'Money\Money')->factory('fromArray');

        $this->assertSame([
            'type' => '\Money\Money',
            'factory' => 'fromArray',
        ], $field->toArray());
    }

    public function testDtoCreate(): void
    {
        $dto = Dto::create('User')->fields(
            Field::int('id')->required(),
            Field::string('name')->required(),
        );

        $this->assertSame('User', $dto->getName());
        $this->assertSame([
            'fields' => [
                'id' => ['type' => 'int', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
            ],
        ], $dto->toArray());
    }

    public function testDtoImmutable(): void
    {
        $dto = Dto::immutable('Event')->fields(
            Field::int('id')->required(),
        );

        $this->assertSame([
            'immutable' => true,
            'fields' => [
                'id' => ['type' => 'int', 'required' => true],
            ],
        ], $dto->toArray());
    }

    public function testDtoExtends(): void
    {
        $dto = Dto::create('FlyingCar')->extends('Car')->fields(
            Field::int('maxAltitude')->default(1000),
        );

        $this->assertSame([
            'extends' => 'Car',
            'fields' => [
                'maxAltitude' => ['type' => 'int', 'defaultValue' => 1000],
            ],
        ], $dto->toArray());
    }

    public function testDtoDeprecated(): void
    {
        $dto = Dto::create('OldUser')->deprecated('Use User instead')->fields(
            Field::int('id'),
        );

        $this->assertSame([
            'deprecated' => 'Use User instead',
            'fields' => [
                'id' => 'int',
            ],
        ], $dto->toArray());
    }

    public function testSchemaCreate(): void
    {
        $schema = Schema::create()
            ->dto(Dto::create('User')->fields(
                Field::int('id')->required(),
                Field::string('email')->required(),
            ))
            ->dto(Dto::create('Address')->fields(
                Field::string('city')->required(),
            ));

        $this->assertSame([
            'User' => [
                'fields' => [
                    'id' => ['type' => 'int', 'required' => true],
                    'email' => ['type' => 'string', 'required' => true],
                ],
            ],
            'Address' => [
                'fields' => [
                    'city' => ['type' => 'string', 'required' => true],
                ],
            ],
        ], $schema->toArray());
    }

    public function testComplexSchema(): void
    {
        $schema = Schema::create()
            ->dto(Dto::create('User')->fields(
                Field::int('id')->required(),
                Field::string('name')->required(),
                Field::string('email')->required(),
                Field::string('phone'),
                Field::bool('active')->default(true),
                Field::array('roles', 'string'),
            ))
            ->dto(Dto::create('Order')->fields(
                Field::int('id')->required(),
                Field::dto('customer', 'User')->required(),
                Field::dto('shippingAddress', 'Address')->required(),
                Field::collection('items', 'OrderItem')->singular('item'),
                Field::float('total')->required(),
            ))
            ->dto(Dto::immutable('ImmutableUser')->fields(
                Field::int('id')->required(),
                Field::string('email')->required(),
            ));

        $result = $schema->toArray();

        // Verify User
        $this->assertArrayHasKey('User', $result);
        $this->assertSame('int', $result['User']['fields']['id']['type']);
        $this->assertTrue($result['User']['fields']['active']['defaultValue']);
        $this->assertSame('string[]', $result['User']['fields']['roles']);

        // Verify Order
        $this->assertArrayHasKey('Order', $result);
        $this->assertSame('User', $result['Order']['fields']['customer']['type']);
        $this->assertTrue($result['Order']['fields']['items']['collection']);
        $this->assertSame('item', $result['Order']['fields']['items']['singular']);

        // Verify ImmutableUser
        $this->assertArrayHasKey('ImmutableUser', $result);
        $this->assertTrue($result['ImmutableUser']['immutable']);
    }

    public function testDtoTraitsSingle(): void
    {
        $dto = Dto::create('User')
            ->traits('App\Traits\UserMethods')
            ->fields(Field::int('id'));

        $this->assertSame([
            'traits' => ['App\Traits\UserMethods'],
            'fields' => [
                'id' => 'int',
            ],
        ], $dto->toArray());
    }

    public function testDtoTraitsMultiple(): void
    {
        $dto = Dto::create('User')
            ->traits('App\Traits\UserMethods', 'App\Traits\Timestamps')
            ->fields(Field::int('id'));

        $this->assertSame([
            'traits' => ['App\Traits\UserMethods', 'App\Traits\Timestamps'],
            'fields' => [
                'id' => 'int',
            ],
        ], $dto->toArray());
    }

    public function testDtoTraitsEmpty(): void
    {
        $dto = Dto::create('User')->fields(Field::int('id'));

        // No traits key when empty
        $this->assertArrayNotHasKey('traits', $dto->toArray());
    }

    public function testFieldMapFrom(): void
    {
        $field = Field::string('emailAddress')->mapFrom('email');

        $this->assertSame([
            'type' => 'string',
            'mapFrom' => 'email',
        ], $field->toArray());
    }

    public function testFieldMapTo(): void
    {
        $field = Field::string('emailAddress')->mapTo('email_address');

        $this->assertSame([
            'type' => 'string',
            'mapTo' => 'email_address',
        ], $field->toArray());
    }

    public function testFieldMapFromAndMapTo(): void
    {
        $field = Field::string('emailAddress')
            ->mapFrom('email')
            ->mapTo('email_address');

        $this->assertSame([
            'type' => 'string',
            'mapFrom' => 'email',
            'mapTo' => 'email_address',
        ], $field->toArray());
    }

    public function testFieldMapWithRequired(): void
    {
        $field = Field::string('emailAddress')
            ->required()
            ->mapFrom('email')
            ->mapTo('email_address');

        $this->assertSame([
            'type' => 'string',
            'required' => true,
            'mapFrom' => 'email',
            'mapTo' => 'email_address',
        ], $field->toArray());
    }

    public function testBenchmarkConfigEquivalence(): void
    {
        // Build using fluent API
        $schema = Schema::create()
            ->dto(Dto::create('User')->fields(
                Field::int('id')->required(),
                Field::string('name')->required(),
                Field::string('email')->required(),
                Field::string('phone'),
                Field::bool('active')->default(true),
                Field::array('roles', 'string'),
            ))
            ->dto(Dto::create('Address')->fields(
                Field::string('street')->required(),
                Field::string('city')->required(),
                Field::string('country')->required(),
                Field::string('zipCode'),
            ))
            ->dto(Dto::create('OrderItem')->fields(
                Field::int('productId')->required(),
                Field::string('name')->required(),
                Field::int('quantity')->required(),
                Field::float('price')->required(),
            ))
            ->dto(Dto::create('Order')->fields(
                Field::int('id')->required(),
                Field::dto('customer', 'User')->required(),
                Field::dto('shippingAddress', 'Address')->required(),
                Field::collection('items', 'OrderItem')->singular('item'),
                Field::float('total')->required(),
                Field::string('status')->required(),
                Field::class('createdAt', DateTimeImmutable::class),
            ))
            ->dto(Dto::immutable('ImmutableUser')->fields(
                Field::int('id')->required(),
                Field::string('name')->required(),
                Field::string('email')->required(),
                Field::string('phone'),
                Field::bool('active')->default(true),
            ));

        // Expected array config (from benchmark/config/dto.php)
        $expected = [
            'User' => [
                'fields' => [
                    'id' => ['type' => 'int', 'required' => true],
                    'name' => ['type' => 'string', 'required' => true],
                    'email' => ['type' => 'string', 'required' => true],
                    'phone' => 'string',
                    'active' => ['type' => 'bool', 'defaultValue' => true],
                    'roles' => 'string[]',
                ],
            ],
            'Address' => [
                'fields' => [
                    'street' => ['type' => 'string', 'required' => true],
                    'city' => ['type' => 'string', 'required' => true],
                    'country' => ['type' => 'string', 'required' => true],
                    'zipCode' => 'string',
                ],
            ],
            'OrderItem' => [
                'fields' => [
                    'productId' => ['type' => 'int', 'required' => true],
                    'name' => ['type' => 'string', 'required' => true],
                    'quantity' => ['type' => 'int', 'required' => true],
                    'price' => ['type' => 'float', 'required' => true],
                ],
            ],
            'Order' => [
                'fields' => [
                    'id' => ['type' => 'int', 'required' => true],
                    'customer' => ['type' => 'User', 'required' => true],
                    'shippingAddress' => ['type' => 'Address', 'required' => true],
                    'items' => ['type' => 'OrderItem[]', 'collection' => true, 'singular' => 'item'],
                    'total' => ['type' => 'float', 'required' => true],
                    'status' => ['type' => 'string', 'required' => true],
                    'createdAt' => '\DateTimeImmutable',
                ],
            ],
            'ImmutableUser' => [
                'immutable' => true,
                'fields' => [
                    'id' => ['type' => 'int', 'required' => true],
                    'name' => ['type' => 'string', 'required' => true],
                    'email' => ['type' => 'string', 'required' => true],
                    'phone' => 'string',
                    'active' => ['type' => 'bool', 'defaultValue' => true],
                ],
            ],
        ];

        $this->assertSame($expected, $schema->toArray());
    }
}
