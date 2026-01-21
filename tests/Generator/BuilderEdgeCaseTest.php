<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use InvalidArgumentException;
use PhpCollective\Dto\Engine\PhpEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Builder edge cases and untested code paths.
 */
class BuilderEdgeCaseTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/builder_edge_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ========== EXTENDS TESTS ==========

    public function testExtendsOtherDto(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Car' => [
        'fields' => [
            'color' => 'string',
        ],
    ],
    'FlyingCar' => [
        'extends' => 'Car',
        'fields' => [
            'maxAltitude' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('FlyingCar', $definitions);
        $this->assertSame('CarDto', $definitions['FlyingCar']['extends']);
    }

    public function testExtendsImmutableDtoMustBeImmutable(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'BaseImmutable' => [
        'immutable' => true,
        'fields' => [
            'id' => 'int',
        ],
    ],
    'ChildMutable' => [
        'extends' => 'BaseImmutable',
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot extend immutable DTO');
        $builder->build($this->tempDir . '/config/');
    }

    public function testImmutableCannotExtendMutable(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'BaseMutable' => [
        'fields' => [
            'id' => 'int',
        ],
    ],
    'ChildImmutable' => [
        'immutable' => true,
        'extends' => 'BaseMutable',
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('immutable DTO cannot extend mutable DTO');
        $builder->build($this->tempDir . '/config/');
    }

    public function testExtendsNonExistentClassThrowsException(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Child' => [
        'extends' => '\NonExistent\ClassName',
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');
        $builder->build($this->tempDir . '/config/');
    }

    public function testExtendsExternalClass(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'CustomDto' => [
        'extends' => '\PhpCollective\Dto\Dto\AbstractDto',
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('CustomDto', $definitions);
        $this->assertSame('\PhpCollective\Dto\Dto\AbstractDto', $definitions['CustomDto']['extends']);
    }

    // ========== PREFIXED CLASS NAMES (Foo/Bar) ==========

    public function testPrefixedDtoName(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Api/UserResponse' => [
        'fields' => [
            'id' => 'int',
            'name' => 'string',
        ],
    ],
    'Api/OrderResponse' => [
        'fields' => [
            'user' => 'Api/UserResponse',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('Api/UserResponse', $definitions);
        $this->assertArrayHasKey('Api/OrderResponse', $definitions);

        // Check that user field references the prefixed DTO correctly
        $userField = $definitions['Api/OrderResponse']['fields']['user'];
        $this->assertSame('\App\Dto\Api\UserResponseDto', $userField['type']);
    }

    // ========== INTERFACE/CLASS TYPE TESTS ==========

    public function testFieldWithDateTimeClass(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Event' => [
        'fields' => [
            'createdAt' => '\DateTime',
            'updatedAt' => '\DateTimeImmutable',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Event']['fields']['createdAt']['isClass']);
        $this->assertTrue($definitions['Event']['fields']['updatedAt']['isClass']);
    }

    public function testFieldWithEnumClass(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/OrderStatus.php';

        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'status' => '\PhpCollective\Dto\Test\Generator\Fixtures\OrderStatus',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Order']['fields']['status']['isClass']);
        $this->assertSame('string', $definitions['Order']['fields']['status']['enum']);
    }

    public function testFieldWithSerializableClass(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Container' => [
        'fields' => [
            'data' => [
                'type' => '\ArrayObject',
                'serialize' => 'array',
            ],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Container']['fields']['data']['isClass']);
        $this->assertSame('array', $definitions['Container']['fields']['data']['serialize']);
    }

    // ========== SINGULARIZE EDGE CASES ==========

    public function testCollectionWithoutSingularThrowsExceptionWhenCannotSingularize(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Container' => [
        'fields' => [
            'sheep' => [
                'type' => 'Item[]',
                'collection' => true,
                // No singular provided, and "sheep" singularizes to "sheep"
            ],
        ],
    ],
    'Item' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot auto-singularize');
        $builder->build($this->tempDir . '/config/');
    }

    public function testCollectionWithExplicitSingular(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Farm' => [
        'fields' => [
            'sheep' => [
                'type' => 'Animal[]',
                'collection' => true,
                'singular' => 'animal',
            ],
        ],
    ],
    'Animal' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertSame('animal', $definitions['Farm']['fields']['sheep']['singular']);
    }

    public function testCollectionSingularCollidesWithField(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'item' => 'string',
            'items' => [
                'type' => 'Product[]',
                'collection' => true,
                // Auto-singularizes to "item" which collides with existing field
            ],
        ],
    ],
    'Product' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('collides');
        $builder->build($this->tempDir . '/config/');
    }

    public function testExplicitSingularCollidesWithField(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'product' => 'string',
            'items' => [
                'type' => 'Product[]',
                'collection' => true,
                'singular' => 'product',
            ],
        ],
    ],
    'Product' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('conflicts with existing field');
        $builder->build($this->tempDir . '/config/');
    }

    // ========== INVALID TYPE TESTS ==========

    public function testInvalidTypeThrowsException(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Test' => [
        'fields' => [
            'invalid' => 'not_a_valid_type',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type');
        $builder->build($this->tempDir . '/config/');
    }

    // ========== ASSOCIATIVE COLLECTION TESTS ==========

    public function testAssociativeCollectionWithKey(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Catalog' => [
        'fields' => [
            'products' => [
                'type' => 'Product[]',
                'collection' => true,
                'singular' => 'product',
                'associative' => true,
                'key' => 'sku',
            ],
        ],
    ],
    'Product' => [
        'fields' => [
            'sku' => 'string',
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $productsField = $definitions['Catalog']['fields']['products'];
        $this->assertTrue($productsField['associative']);
        $this->assertSame('sku', $productsField['key']);
    }

    // ========== ENUM TYPE TESTS ==========

    public function testFieldWithUnitEnum(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/UnitEnum.php';

        $configContent = <<<'PHP'
<?php
return [
    'Task' => [
        'fields' => [
            'status' => '\PhpCollective\Dto\Test\Generator\Fixtures\UnitEnum',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Task']['fields']['status']['isClass']);
        $this->assertSame('unit', $definitions['Task']['fields']['status']['enum']);
    }

    public function testFieldWithIntBackedEnum(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/IntBackedEnum.php';

        $configContent = <<<'PHP'
<?php
return [
    'Priority' => [
        'fields' => [
            'level' => '\PhpCollective\Dto\Test\Generator\Fixtures\IntBackedEnum',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Priority']['fields']['level']['isClass']);
        $this->assertSame('int', $definitions['Priority']['fields']['level']['enum']);
    }

    // ========== DETECT SERIALIZE TESTS ==========

    public function testFieldWithFromArrayToArrayClass(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/FromArrayToArrayClass.php';

        $configContent = <<<'PHP'
<?php
return [
    'Wrapper' => [
        'fields' => [
            'data' => '\PhpCollective\Dto\Test\Generator\Fixtures\FromArrayToArrayClass',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Wrapper']['fields']['data']['isClass']);
        $this->assertSame('FromArrayToArray', $definitions['Wrapper']['fields']['data']['serialize']);
    }

    public function testFieldWithJsonSerializableClass(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/JsonSerializableClass.php';

        $configContent = <<<'PHP'
<?php
return [
    'Wrapper' => [
        'fields' => [
            'data' => '\PhpCollective\Dto\Test\Generator\Fixtures\JsonSerializableClass',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Wrapper']['fields']['data']['isClass']);
        // JsonSerializable classes return null for serialize (handled naturally by json_encode)
        $this->assertNull($definitions['Wrapper']['fields']['data']['serialize']);
    }

    public function testFieldWithToArrayClass(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/ToArrayClass.php';

        $configContent = <<<'PHP'
<?php
return [
    'Wrapper' => [
        'fields' => [
            'data' => '\PhpCollective\Dto\Test\Generator\Fixtures\ToArrayClass',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Wrapper']['fields']['data']['isClass']);
        $this->assertSame('array', $definitions['Wrapper']['fields']['data']['serialize']);
    }

    public function testFieldWithPlainClass(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/PlainClass.php';

        $configContent = <<<'PHP'
<?php
return [
    'Wrapper' => [
        'fields' => [
            'data' => '\PhpCollective\Dto\Test\Generator\Fixtures\PlainClass',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertTrue($definitions['Wrapper']['fields']['data']['isClass']);
        // Plain classes without any serialization method return null
        $this->assertNull($definitions['Wrapper']['fields']['data']['serialize']);
    }

    public function testFieldWithExplicitSerializeOverridesAutoDetect(): void
    {
        $fixturesDir = __DIR__ . '/Fixtures';
        require_once $fixturesDir . '/ToArrayClass.php';

        $configContent = <<<'PHP'
<?php
return [
    'Wrapper' => [
        'fields' => [
            'data' => [
                'type' => '\PhpCollective\Dto\Test\Generator\Fixtures\ToArrayClass',
                'serialize' => 'json',
            ],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Explicit serialize setting should not be overridden by detectSerialize
        $this->assertSame('json', $definitions['Wrapper']['fields']['data']['serialize']);
    }

    // ========== TRAITS TESTS ==========

    public function testDtoWithSingleTrait(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'traits' => 'App\Traits\UserMethods',
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('traits', $definitions['User']);
        $this->assertSame(['\\App\\Traits\\UserMethods'], $definitions['User']['traits']);
    }

    public function testDtoWithMultipleTraitsArray(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'traits' => ['App\Traits\UserMethods', 'App\Traits\Timestamps'],
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('traits', $definitions['User']);
        $this->assertSame(['\\App\\Traits\\UserMethods', '\\App\\Traits\\Timestamps'], $definitions['User']['traits']);
    }

    public function testDtoWithCommaSeparatedTraits(): void
    {
        // This format is useful for XML where arrays aren't natural
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'traits' => 'App\Traits\UserMethods, App\Traits\Timestamps',
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('traits', $definitions['User']);
        $this->assertSame(['\\App\\Traits\\UserMethods', '\\App\\Traits\\Timestamps'], $definitions['User']['traits']);
    }

    public function testDtoWithoutTraits(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('traits', $definitions['User']);
        $this->assertSame([], $definitions['User']['traits']);
    }

    public function testDtoWithTraitsAlreadyWithBackslash(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'traits' => ['\App\Traits\UserMethods'],
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Should not double up backslashes
        $this->assertSame(['\\App\\Traits\\UserMethods'], $definitions['User']['traits']);
    }

    // ========== PROPERTY MAPPING TESTS ==========

    public function testFieldWithMapFrom(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'emailAddress' => [
                'type' => 'string',
                'mapFrom' => 'email',
            ],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertSame('email', $definitions['User']['fields']['emailAddress']['mapFrom']);
        $this->assertSame('email', $definitions['User']['metaData']['emailAddress']['mapFrom']);
    }

    public function testFieldWithMapTo(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'emailAddress' => [
                'type' => 'string',
                'mapTo' => 'email_address',
            ],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertSame('email_address', $definitions['User']['fields']['emailAddress']['mapTo']);
        $this->assertSame('email_address', $definitions['User']['metaData']['emailAddress']['mapTo']);
    }

    public function testFieldWithMapFromAndMapTo(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'ApiResponse' => [
        'fields' => [
            'userName' => [
                'type' => 'string',
                'mapFrom' => 'user_name',
                'mapTo' => 'username',
            ],
            'createdAt' => [
                'type' => 'string',
                'mapFrom' => 'created_at',
                'mapTo' => 'timestamp',
            ],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // userName field
        $this->assertSame('user_name', $definitions['ApiResponse']['fields']['userName']['mapFrom']);
        $this->assertSame('username', $definitions['ApiResponse']['fields']['userName']['mapTo']);

        // createdAt field
        $this->assertSame('created_at', $definitions['ApiResponse']['fields']['createdAt']['mapFrom']);
        $this->assertSame('timestamp', $definitions['ApiResponse']['fields']['createdAt']['mapTo']);
    }

    public function testFieldWithoutMappingHasNullValues(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertNull($definitions['User']['fields']['name']['mapFrom']);
        $this->assertNull($definitions['User']['fields']['name']['mapTo']);
    }

    // ========== SCALAR AND RETURN TYPES TESTS ==========

    public function testScalarAndReturnTypesEnabledByDefault(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
            'age' => 'int',
            'active' => 'bool',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // When scalarAndReturnTypes is true (default), typeHint and returnTypeHint should be set
        $this->assertSame('string', $definitions['User']['fields']['name']['typeHint']);
        $this->assertSame('string', $definitions['User']['fields']['name']['returnTypeHint']);
        $this->assertSame('?string', $definitions['User']['fields']['name']['nullableTypeHint']);

        $this->assertSame('int', $definitions['User']['fields']['age']['typeHint']);
        $this->assertSame('int', $definitions['User']['fields']['age']['returnTypeHint']);

        $this->assertSame('bool', $definitions['User']['fields']['active']['typeHint']);
        $this->assertSame('bool', $definitions['User']['fields']['active']['returnTypeHint']);
    }

    public function testScalarAndReturnTypesDisabled(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
            'age' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'scalarAndReturnTypes' => false,
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // When scalarAndReturnTypes is false, returnTypeHint and nullableTypeHint should be null
        $this->assertNull($definitions['User']['fields']['name']['typeHint']);
        $this->assertNull($definitions['User']['fields']['name']['returnTypeHint']);
        $this->assertNull($definitions['User']['fields']['name']['nullableTypeHint']);

        $this->assertNull($definitions['User']['fields']['age']['typeHint']);
        $this->assertNull($definitions['User']['fields']['age']['returnTypeHint']);
    }

    public function testScalarAndReturnTypesDisabledStillAllowsClassTypes(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Event' => [
        'fields' => [
            'name' => 'string',
            'createdAt' => '\DateTimeImmutable',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'scalarAndReturnTypes' => false,
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Scalar types should have no type hints
        $this->assertNull($definitions['Event']['fields']['name']['typeHint']);
        $this->assertNull($definitions['Event']['fields']['name']['returnTypeHint']);

        // Class types should still have type hints (classes are always safe)
        $this->assertSame('\DateTimeImmutable', $definitions['Event']['fields']['createdAt']['typeHint']);
        // But returnTypeHint requires scalarAndReturnTypes to be true
        $this->assertNull($definitions['Event']['fields']['createdAt']['returnTypeHint']);
    }

    public function testRequiredFieldWithScalarTypes(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'id' => [
                'type' => 'int',
                'required' => true,
            ],
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Required field should not have nullableTypeHint
        $this->assertSame('int', $definitions['User']['fields']['id']['typeHint']);
        $this->assertSame('int', $definitions['User']['fields']['id']['returnTypeHint']);
        $this->assertNull($definitions['User']['fields']['id']['nullableTypeHint']);
        $this->assertFalse($definitions['User']['fields']['id']['nullable']);

        // Non-required field should have nullableTypeHint
        $this->assertSame('string', $definitions['User']['fields']['name']['typeHint']);
        $this->assertSame('?string', $definitions['User']['fields']['name']['nullableTypeHint']);
        $this->assertTrue($definitions['User']['fields']['name']['nullable']);
    }

    public function testUnionTypeWithScalarTypes(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Response' => [
        'fields' => [
            'id' => [
                'type' => 'int|string',
            ],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Union types should use |null instead of ?
        $this->assertSame('int|string', $definitions['Response']['fields']['id']['typeHint']);
        $this->assertSame('int|string|null', $definitions['Response']['fields']['id']['nullableTypeHint']);
    }

    public function testArrayUnionTypesConvertToArray(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Mixed' => [
        'fields' => [
            'data' => ['type' => 'string[]|int[]'],
            'items' => ['type' => 'float[]|bool[]'],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // PHP doesn't support array notation in union types (string[]|int[] is invalid)
        // Should convert to 'array' type hint
        $this->assertSame('array', $definitions['Mixed']['fields']['data']['typeHint']);
        $this->assertSame('array', $definitions['Mixed']['fields']['items']['typeHint']);

        // Original type should be preserved for docblock
        $this->assertSame('string[]|int[]', $definitions['Mixed']['fields']['data']['type']);
        $this->assertSame('float[]|bool[]', $definitions['Mixed']['fields']['items']['type']);
    }

    public function testMixedArrayAndScalarUnionTypesConvertToArray(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Response' => [
        'fields' => [
            'value' => ['type' => 'string[]|int'],
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Mixed array and scalar union should also convert to array
        $this->assertSame('array', $definitions['Response']['fields']['value']['typeHint']);
    }

    public function testTypedConstantsEnabled(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'typedConstants' => true,
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // typedConstants is passed to renderer, verify it's in config
        $this->assertTrue($config->get('typedConstants'));
        $this->assertArrayHasKey('User', $definitions);
    }

    public function testTypedConstantsDisabled(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'typedConstants' => false,
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Default is false
        $this->assertFalse($config->get('typedConstants'));
        $this->assertArrayHasKey('User', $definitions);
    }

    public function testDefaultCollectionType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'items' => [
                'type' => 'Item[]',
                'singular' => 'item',
            ],
        ],
    ],
    'Item' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'defaultCollectionType' => '\ArrayObject',
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Collection type should be ArrayObject (default)
        $this->assertSame('\ArrayObject', $definitions['Order']['fields']['items']['collectionType']);
    }

    public function testCustomCollectionType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'items' => [
                'type' => 'Item[]',
                'singular' => 'item',
            ],
        ],
    ],
    'Item' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'defaultCollectionType' => '\Doctrine\Common\Collections\ArrayCollection',
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Custom collection type should be used
        $this->assertSame('\Doctrine\Common\Collections\ArrayCollection', $definitions['Order']['fields']['items']['collectionType']);
    }

    public function testDebugModeEnabled(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'debug' => true,
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // In debug mode, metadata should contain all fields (not filtered)
        $metadata = $definitions['User']['fields']['name'];
        // Debug mode includes extra fields like 'value' in metadata
        $this->assertTrue($config->get('debug'));
        $this->assertArrayHasKey('name', $definitions['User']['fields']);
    }

    public function testDebugModeDisabled(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'debug' => false,
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Default is false
        $this->assertFalse($config->get('debug'));
        $this->assertArrayHasKey('User', $definitions);
    }

    public function testCustomSuffix(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'suffix' => 'Transfer',
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Class name should use custom suffix
        $this->assertSame('UserTransfer', $definitions['User']['className']);
    }

    public function testDefaultSuffix(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Default suffix is 'Dto'
        $this->assertSame('UserDto', $definitions['User']['className']);
    }

    public function testSuffixAffectsExtends(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'BaseUser' => [
        'fields' => [
            'id' => 'int',
        ],
    ],
    'AdminUser' => [
        'extends' => 'BaseUser',
        'fields' => [
            'role' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig([
            'namespace' => 'App',
            'suffix' => 'Transfer',
        ]);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Extended class name should also use custom suffix
        $this->assertSame('AdminUserTransfer', $definitions['AdminUser']['className']);
        $this->assertSame('BaseUserTransfer', $definitions['AdminUser']['extends']);
    }

    // ========== UNDERSCORE-PREFIXED FIELD NAME TESTS ==========

    public function testUnderscorePrefixedFieldNameIsAllowed(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Tag' => [
        'fields' => [
            '_joinData' => 'JoinData',
        ],
    ],
    'JoinData' => [
        'fields' => [
            'count' => 'int',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('Tag', $definitions);
        $this->assertArrayHasKey('_joinData', $definitions['Tag']['fields']);

        // Field name should be preserved (for property name and toArray)
        $this->assertSame('_joinData', $definitions['Tag']['fields']['_joinData']['name']);
    }

    public function testUnderscorePrefixedFieldNameCollisionThrowsException(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            '_data' => 'string',
            'data' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Field name collision in 'User' DTO");
        $this->expectExceptionMessage("fields '_data' and 'data' would generate identical method names");

        $builder->build($this->tempDir . '/config/');
    }

    public function testMultipleUnderscorePrefixedFieldsAreAllowed(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Entity' => [
        'fields' => [
            '_joinData' => 'JoinData',
            '_matchingData' => 'MatchingData',
            'regularField' => 'string',
        ],
    ],
    'JoinData' => [
        'fields' => [
            'id' => 'int',
        ],
    ],
    'MatchingData' => [
        'fields' => [
            'score' => 'float',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('Entity', $definitions);
        $this->assertArrayHasKey('_joinData', $definitions['Entity']['fields']);
        $this->assertArrayHasKey('_matchingData', $definitions['Entity']['fields']);
        $this->assertArrayHasKey('regularField', $definitions['Entity']['fields']);
    }
}
