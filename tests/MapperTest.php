<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test;

use InvalidArgumentException;
use JsonSerializable;
use PhpCollective\Dto\Dto\Dto;
use PhpCollective\Dto\Dto\FromArrayToArrayInterface;
use PhpCollective\Dto\Mapper;
use PhpCollective\Dto\ObjectFactory;
use PhpCollective\Dto\Test\TestDto\AdvancedDto;
use PhpCollective\Dto\Test\TestDto\SimpleDto;
use PHPUnit\Framework\TestCase;
use stdClass;

class MapperTest extends TestCase
{
    public function testMapFromArrayReturnsObjectFactory(): void
    {
        $factory = Mapper::map(['name' => 'Test']);
        $this->assertInstanceOf(ObjectFactory::class, $factory);
    }

    public function testMapArrayToDto(): void
    {
        $dto = Mapper::map(['name' => 'Test', 'count' => 5])->to(SimpleDto::class);
        $this->assertInstanceOf(SimpleDto::class, $dto);
        $this->assertSame('Test', $dto->getName());
        $this->assertSame(5, $dto->getCount());
    }

    public function testMapDtoToDtoCopiesTouchedFields(): void
    {
        $source = new SimpleDto();
        $source->setName('Copy');

        $copy = Mapper::map($source)->to(SimpleDto::class);

        $this->assertNotSame($source, $copy);
        $this->assertSame('Copy', $copy->getName());
        $this->assertNull($copy->getCount(), 'Untouched fields must not leak into the copy');
    }

    public function testMapJsonStringToDto(): void
    {
        $dto = Mapper::map('{"name":"Json","count":42}')->to(SimpleDto::class);
        $this->assertSame('Json', $dto->getName());
        $this->assertSame(42, $dto->getCount());
    }

    public function testMapJsonSerializableToDto(): void
    {
        $source = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['name' => 'JsonSerializable', 'count' => 7];
            }
        };

        $dto = Mapper::map($source)->to(SimpleDto::class);
        $this->assertSame('JsonSerializable', $dto->getName());
        $this->assertSame(7, $dto->getCount());
    }

    public function testMapFromArrayToArrayInterfaceSource(): void
    {
        $source = new class implements FromArrayToArrayInterface {
            public static function createFromArray(array $array): self
            {
                return new self();
            }

            public function toArray(): array
            {
                return ['name' => 'Interface', 'count' => 3];
            }
        };

        $dto = Mapper::map($source)->to(SimpleDto::class);
        $this->assertSame('Interface', $dto->getName());
        $this->assertSame(3, $dto->getCount());
    }

    public function testMapPlainObjectToDto(): void
    {
        $source = new stdClass();
        $source->name = 'Plain';
        $source->count = 9;

        $dto = Mapper::map($source)->to(SimpleDto::class);
        $this->assertSame('Plain', $dto->getName());
        $this->assertSame(9, $dto->getCount());
    }

    public function testMapWithKeyTypeUnderscored(): void
    {
        $dto = Mapper::map(['plain_data' => 'underscore-source'])
            ->withKeyType(Dto::TYPE_UNDERSCORED)
            ->to(AdvancedDto::class);

        $this->assertSame('underscore-source', $dto->getPlainData()?->value);
    }

    public function testMapWithOnlyFiltersFields(): void
    {
        $dto = Mapper::map(['name' => 'Filtered', 'count' => 99])
            ->only(['name'])
            ->to(SimpleDto::class);

        $this->assertSame('Filtered', $dto->getName());
        $this->assertNull($dto->getCount(), 'Fields outside only() must not be hydrated');
    }

    public function testMapWithIgnoreMissingFalseRejectsUnknownKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Mapper::map(['name' => 'X', 'not_a_field' => 'nope'])
            ->ignoreMissing(false)
            ->to(SimpleDto::class);
    }

    public function testMapDefaultsToIgnoreMissingTrue(): void
    {
        $dto = Mapper::map(['name' => 'OK', 'extra_unknown_key' => 'ignored'])
            ->to(SimpleDto::class);

        $this->assertSame('OK', $dto->getName());
    }

    public function testMapToNonDtoTargetThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a subclass of');

        Mapper::map(['name' => 'X'])->to(stdClass::class);
    }

    public function testMapUnsupportedSourceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot map source of type');

        Mapper::map(42)->to(SimpleDto::class);
    }

    public function testMapInvalidJsonStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('could not be decoded');

        Mapper::map('not-valid-json')->to(SimpleDto::class);
    }

    public function testMapJsonSerializableReturningScalarThrows(): void
    {
        $source = new class implements JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'scalar-instead-of-array';
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported payload');

        Mapper::map($source)->to(SimpleDto::class);
    }

    public function testMapperToArrayHelperIsReusable(): void
    {
        $array = Mapper::toArray(['a' => 1]);
        $this->assertSame(['a' => 1], $array);

        $dto = new SimpleDto();
        $dto->setName('helper');
        $this->assertSame(['name' => 'helper'], Mapper::toArray($dto));
    }

    public function testDtoFromShortcutReturnsTypedInstance(): void
    {
        $dto = SimpleDto::from(['name' => 'Shortcut', 'count' => 1]);
        $this->assertInstanceOf(SimpleDto::class, $dto);
        $this->assertSame('Shortcut', $dto->getName());
    }

    public function testDtoFromShortcutAcceptsAnotherDto(): void
    {
        $source = new SimpleDto();
        $source->setName('From');

        $copy = SimpleDto::from($source);
        $this->assertSame('From', $copy->getName());
    }

    public function testDtoFromShortcutAcceptsJsonString(): void
    {
        $dto = SimpleDto::from('{"name":"json-shortcut"}');
        $this->assertSame('json-shortcut', $dto->getName());
    }

    public function testDtoFromShortcutIgnoresUnknownKeys(): void
    {
        // The shortcut defaults to ignoreMissing = true so callers can feed
        // arbitrary request payloads without pre-filtering.
        $dto = SimpleDto::from(['name' => 'X', 'bogus_field' => 'ignored']);
        $this->assertSame('X', $dto->getName());
    }

    public function testMapRejectsListArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected an associative array');

        Mapper::map([1, 2, 3])->to(SimpleDto::class);
    }

    public function testMapRejectsJsonListString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected an associative array');

        Mapper::map('[1, 2, 3]')->to(SimpleDto::class);
    }

    public function testMapRejectsArrayWithIntegerKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('all keys must be strings');

        // Not a list (gap in keys) but still has int keys — reject.
        Mapper::map([0 => 'a', 2 => 'b'])->to(SimpleDto::class);
    }

    public function testMapRejectsJsonSerializableReturningList(): void
    {
        $source = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return [1, 2, 3];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected an associative array');

        Mapper::map($source)->to(SimpleDto::class);
    }

    public function testMapAcceptsEmptyArray(): void
    {
        // Empty array is ambiguous (both list and associative) — allow it.
        $dto = Mapper::map([])->to(SimpleDto::class);
        $this->assertNull($dto->getName());
    }

    public function testOnlyMatchesSourceKeysBeforeKeyTypeInflection(): void
    {
        // With withKeyType(TYPE_UNDERSCORED) the source keys are underscored,
        // and only() filters on those raw source keys — NOT on camelCase DTO
        // field names. Passing 'plainData' filters everything out; passing
        // 'plain_data' correctly selects the field.
        $dtoWrongKey = Mapper::map(['plain_data' => 'x'])
            ->withKeyType(Dto::TYPE_UNDERSCORED)
            ->only(['plainData'])
            ->to(AdvancedDto::class);
        $this->assertNull($dtoWrongKey->getPlainData());

        $dtoRightKey = Mapper::map(['plain_data' => 'x'])
            ->withKeyType(Dto::TYPE_UNDERSCORED)
            ->only(['plain_data'])
            ->to(AdvancedDto::class);
        $this->assertSame('x', $dtoRightKey->getPlainData()?->value);
    }
}
