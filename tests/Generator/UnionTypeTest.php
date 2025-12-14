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
use PhpCollective\Dto\Generator\TypeScriptGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PHP union type support.
 */
class UnionTypeTest extends TestCase
{
    protected string $tempDir;

    /**
     * @var resource
     */
    protected $stdout;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/union_type_test_' . uniqid();
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

    public function testBuilderParsesUnionType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'UnionTest' => [
        'fields' => [
            'value' => 'int|string',
            'data' => 'int|float|string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        $this->assertArrayHasKey('UnionTest', $definitions);
        $fields = $definitions['UnionTest']['fields'];

        // Type should remain as union
        $this->assertSame('int|string', $fields['value']['type']);
        $this->assertSame('int|float|string', $fields['data']['type']);

        // TypeHint should be the union type
        $this->assertSame('int|string', $fields['value']['typeHint']);
        $this->assertSame('int|float|string', $fields['data']['typeHint']);

        // ReturnTypeHint should also be the union type
        $this->assertSame('int|string', $fields['value']['returnTypeHint']);
        $this->assertSame('int|float|string', $fields['data']['returnTypeHint']);
    }

    public function testBuilderNullableUnionType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'NullableUnion' => [
        'fields' => [
            'value' => [
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

        $field = $definitions['NullableUnion']['fields']['value'];

        // Nullable fields should use |null suffix instead of ? prefix
        $this->assertTrue($field['nullable']);
        $this->assertSame('int|string|null', $field['nullableTypeHint']);
        $this->assertSame('int|string|null', $field['nullableReturnTypeHint']);
    }

    public function testBuilderRequiredUnionType(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'RequiredUnion' => [
        'fields' => [
            'value' => [
                'type' => 'int|string',
                'required' => true,
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

        $field = $definitions['RequiredUnion']['fields']['value'];

        // Required fields are not nullable
        $this->assertFalse($field['nullable']);
        $this->assertSame('int|string', $field['typeHint']);
        $this->assertNull($field['nullableTypeHint']);
    }

    public function testGeneratorCreatesUnionTypeHints(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'UnionDto' => [
        'fields' => [
            'identifier' => [
                'type' => 'int|string',
                'required' => true,
            ],
            'optionalValue' => 'int|float',
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

        $generatedFile = $this->tempDir . '/src/Dto/UnionDtoDto.php';
        $this->assertFileExists($generatedFile);

        $content = file_get_contents($generatedFile);

        // Setter for required union type - no nullable
        $this->assertStringContainsString('public function setIdentifier(int|string $identifier)', $content);

        // Getter for required union type - returns the type directly
        $this->assertStringContainsString('public function getIdentifier(): int|string', $content);

        // Setter for optional union type - uses |null suffix with default = null
        $this->assertStringContainsString('public function setOptionalValue(int|float|null $optionalValue = null)', $content);

        // Getter for optional union type - uses |null suffix
        $this->assertStringContainsString('public function getOptionalValue(): int|float|null', $content);
    }

    public function testGeneratedDtoWithUnionTypesIsValid(): void
    {
        $configContent = <<<'PHP'
<?php
return [
    'ValidUnion' => [
        'fields' => [
            'id' => [
                'type' => 'int|string',
                'required' => true,
            ],
            'score' => 'int|float',
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

        $generatedFile = $this->tempDir . '/src/Dto/ValidUnionDto.php';
        $this->assertFileExists($generatedFile);

        // Verify the generated PHP is syntactically valid
        $output = [];
        $returnCode = 0;
        exec('php -l ' . escapeshellarg($generatedFile) . ' 2>&1', $output, $returnCode);
        $this->assertSame(0, $returnCode, 'Generated PHP is not syntactically valid: ' . implode("\n", $output));
    }

    public function testUnionTypeWithMixed(): void
    {
        // Mixed in union is not valid in PHP, but we need to handle it gracefully
        $configContent = <<<'PHP'
<?php
return [
    'MixedField' => [
        'fields' => [
            'data' => 'mixed',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $configContent);

        $config = new ArrayConfig(['namespace' => 'App']);
        $engine = new PhpEngine();
        $builder = new Builder($engine, $config);

        $definitions = $builder->build($this->tempDir . '/config/');

        // Mixed should not generate a type hint (it's PHP 8.0+ standalone, not in unions)
        $field = $definitions['MixedField']['fields']['data'];
        $this->assertSame('mixed', $field['type']);
        $this->assertNull($field['typeHint']);
    }

    public function testTypeScriptGeneratorWithUnionTypes(): void
    {
        $definitions = [
            'UnionTest' => [
                'name' => 'UnionTest',
                'fields' => [
                    'value' => [
                        'name' => 'value',
                        'type' => 'int|string',
                        'required' => true,
                    ],
                    'data' => [
                        'name' => 'data',
                        'type' => 'int|float|string',
                    ],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');

        // TypeScript should convert PHP union to TS union
        $this->assertStringContainsString('value: number | string;', $content);
        $this->assertStringContainsString('data?: number | string;', $content);
    }
}
