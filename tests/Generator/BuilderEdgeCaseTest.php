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
        $this->expectExceptionMessage('Extended DTO is immutable');
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
        $this->expectExceptionMessage('Extended DTO is not immutable');
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
        $this->expectExceptionMessage('Class does not seem to exist');
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
        $this->expectExceptionMessage('cannot be singularized');
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
        $this->expectExceptionMessage('already exists as field');
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
        $this->expectExceptionMessage('Invalid field attribute');
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
}
