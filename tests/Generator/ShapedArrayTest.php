<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Engine\PhpEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\Generator;
use PhpCollective\Dto\Generator\IoInterface;
use PhpCollective\Dto\Generator\TwigRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for shaped array type generation on toArray()/createFromArray().
 */
class ShapedArrayTest extends TestCase
{
    protected string $tempDir;

    /**
     * @var resource
     */
    protected $stdout;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/shaped_array_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/config', 0777, true);
        $this->stdout = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        fclose($this->stdout);
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

    protected function createIo(): ConsoleIo
    {
        return new ConsoleIo(IoInterface::QUIET, $this->stdout, $this->stdout);
    }

    public function testBuilderGeneratesArrayShape(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'id' => 'int',
            'name' => 'string',
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

        // Should have arrayShape at the DTO level
        $this->assertArrayHasKey('arrayShape', $definitions['User']);
        $this->assertSame('array{id: int|null, name: string|null, active: bool|null}', $definitions['User']['arrayShape']);
    }

    public function testBuilderGeneratesArrayShapeWithNestedDto(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'id' => 'int',
            'customer' => 'Customer',
        ],
    ],
    'Customer' => [
        'fields' => [
            'name' => 'string',
            'email' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Order should have nested shape for Customer
        $this->assertArrayHasKey('arrayShape', $definitions['Order']);
        $this->assertStringContainsString('customer: array{name: string|null, email: string|null}', $definitions['Order']['arrayShape']);
    }

    public function testBuilderGeneratesArrayShapeWithCollection(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'id' => 'int',
            'items' => [
                'type' => 'Item[]',
                'collection' => true,
                'singular' => 'item',
            ],
        ],
    ],
    'Item' => [
        'fields' => [
            'name' => 'string',
            'price' => 'float',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Order should have array type for items collection
        $this->assertArrayHasKey('arrayShape', $definitions['Order']);
        $this->assertStringContainsString('items: array<int, array{name: string|null, price: float|null}>', $definitions['Order']['arrayShape']);
    }

    public function testGeneratedDtoHasShapedArrayMethods(): void
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
            'email' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'TestApp']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);
        $renderer = new TwigRenderer(null, $config);

        $generator = new Generator($builder, $renderer, $this->createIo(), $config);
        $generator->generate($this->tempDir . '/config/', $this->tempDir . '/src/');

        $generatedFile = $this->tempDir . '/src/Dto/UserDto.php';
        $this->assertFileExists($generatedFile);

        $content = file_get_contents($generatedFile);
        $this->assertIsString($content);

        // Should have overridden toArray with shaped array return type
        $this->assertStringContainsString('@return array{id: int, name: string|null, email: string|null}', $content);
        $this->assertStringContainsString('#[\Override]', $content);
        $this->assertStringContainsString('public function toArray(', $content);

        // Should have overridden createFromArray with shaped array param type
        $this->assertStringContainsString('@param array{id: int, name: string|null, email: string|null} $data', $content);
        $this->assertStringContainsString('public static function createFromArray(', $content);
    }

    public function testGeneratedDtoWithNestedShapedArrays(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'id' => 'int',
            'customer' => 'Customer',
            'items' => [
                'type' => 'Item[]',
                'collection' => true,
                'collectionType' => 'array',
                'singular' => 'item',
            ],
        ],
    ],
    'Customer' => [
        'fields' => [
            'name' => 'string',
        ],
    ],
    'Item' => [
        'fields' => [
            'name' => 'string',
            'price' => 'float',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'TestApp']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);
        $renderer = new TwigRenderer(null, $config);

        $generator = new Generator($builder, $renderer, $this->createIo(), $config);
        $generator->generate($this->tempDir . '/config/', $this->tempDir . '/src/');

        $generatedFile = $this->tempDir . '/src/Dto/OrderDto.php';
        $this->assertFileExists($generatedFile);

        $content = file_get_contents($generatedFile);
        $this->assertIsString($content);

        // Should have nested shapes
        $this->assertStringContainsString('customer: array{name: string|null}', $content);
        $this->assertStringContainsString('items: array<int, array{name: string|null, price: float|null}>', $content);
    }
}
