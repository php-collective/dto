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
 * Tests for PHPDoc generic type annotations.
 */
class GenericPhpDocTest extends TestCase
{
    protected string $tempDir;

    /**
     * @var resource
     */
    protected $stdout;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/generic_phpdoc_test_' . uniqid();
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

    public function testBuilderGeneratesDocBlockTypeForArrayCollection(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
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
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $itemsField = $definitions['Order']['fields']['items'];

        // Should have generic docBlockType
        $this->assertArrayHasKey('docBlockType', $itemsField);
        $this->assertSame('\ArrayObject<int, \App\Dto\ItemDto>', $itemsField['docBlockType']);
    }

    public function testBuilderGeneratesDocBlockTypeForTypedArray(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'tags' => 'string[]',
            'scores' => 'int[]',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $tagsField = $definitions['User']['fields']['tags'];
        $scoresField = $definitions['User']['fields']['scores'];

        $this->assertArrayHasKey('docBlockType', $tagsField);
        $this->assertSame('array<int, string>', $tagsField['docBlockType']);

        $this->assertArrayHasKey('docBlockType', $scoresField);
        $this->assertSame('array<int, int>', $scoresField['docBlockType']);
    }

    public function testBuilderGeneratesDocBlockTypeForAssociativeCollection(): void
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

        $definitions = $builder->build($this->tempDir . '/config/');

        $productsField = $definitions['Catalog']['fields']['products'];

        // Should have string key type for associative collection
        $this->assertArrayHasKey('docBlockType', $productsField);
        $this->assertSame('\ArrayObject<string, \App\Dto\ProductDto>', $productsField['docBlockType']);

        // Should have keyType set for collections
        $this->assertArrayHasKey('keyType', $productsField);
        $this->assertSame('string', $productsField['keyType']);
    }

    public function testGeneratedAssociativeCollectionUsesCorrectKeyType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Catalog' => [
        'fields' => [
            'products' => [
                'type' => 'Product[]',
                'collection' => true,
                'collectionType' => 'array',
                'singular' => 'product',
                'associative' => true,
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

        $config = new ArrayConfig(['namespace' => 'TestApp']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);
        $renderer = new TwigRenderer(null, $config);

        $generator = new Generator($builder, $renderer, $this->createIo(), $config);
        $generator->generate($this->tempDir . '/config/', $this->tempDir . '/src/');

        $generatedFile = $this->tempDir . '/src/Dto/CatalogDto.php';
        $this->assertFileExists($generatedFile);

        $content = file_get_contents($generatedFile);

        // Associative collection should have @param string $key (not string|int)
        $this->assertStringContainsString('@param string $key', $content);
        $this->assertStringNotContainsString('@param string|int $key', $content);
    }

    public function testGeneratedDtoUsesGenericPhpDocTypes(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'items' => [
                'type' => 'Item[]',
                'collection' => true,
                'singular' => 'item',
            ],
            'tags' => 'string[]',
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

        $config = new ArrayConfig(['namespace' => 'TestApp']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);
        $renderer = new TwigRenderer(null, $config);

        $generator = new Generator($builder, $renderer, $this->createIo(), $config);
        $generator->generate($this->tempDir . '/config/', $this->tempDir . '/src/');

        $generatedFile = $this->tempDir . '/src/Dto/OrderDto.php';
        $this->assertFileExists($generatedFile);

        $content = file_get_contents($generatedFile);

        // Class-level @property annotations should use generics
        $this->assertStringContainsString('@property \ArrayObject<int, \TestApp\Dto\ItemDto>', $content);
        $this->assertStringContainsString('@property array<int, string>', $content);

        // Property @var annotations should use generics
        $this->assertStringContainsString('@var \ArrayObject<int, \TestApp\Dto\ItemDto>', $content);
        $this->assertStringContainsString('@var array<int, string>', $content);

        // Method @return annotations should use generics
        $this->assertStringContainsString('@return \ArrayObject<int, \TestApp\Dto\ItemDto>', $content);
        $this->assertStringContainsString('@return array<int, string>', $content);
    }

    public function testGeneratedDtoWithPlainArrayCollectionType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Order' => [
        'fields' => [
            'items' => [
                'type' => 'Item[]',
                'collection' => true,
                'collectionType' => 'array',
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

        $config = new ArrayConfig(['namespace' => 'TestApp']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $itemsField = $definitions['Order']['fields']['items'];

        // Should use array<int, ElementType> when collectionType is 'array'
        $this->assertArrayHasKey('docBlockType', $itemsField);
        $this->assertSame('array<int, \TestApp\Dto\ItemDto>', $itemsField['docBlockType']);
    }

    public function testScalarFieldsDoNotHaveDocBlockType(): void
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

        // Scalar fields should not have docBlockType set
        $this->assertArrayNotHasKey('docBlockType', $definitions['User']['fields']['id']);
        $this->assertArrayNotHasKey('docBlockType', $definitions['User']['fields']['name']);
        $this->assertArrayNotHasKey('docBlockType', $definitions['User']['fields']['active']);
    }

    public function testNullableSingularIncludesNullInDocBlockType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'Meta' => [
        'fields' => [
            'tags' => [
                'type' => '?string[]',
                'collectionType' => 'array',
                'associative' => true,
                'singular' => 'tag',
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

        $tagsField = $definitions['Meta']['fields']['tags'];

        // Nullable singular should include |null in docBlockType
        $this->assertArrayHasKey('docBlockType', $tagsField);
        $this->assertSame('array<string, string|null>', $tagsField['docBlockType']);
        $this->assertTrue($tagsField['singularNullable']);
    }
}
