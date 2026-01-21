<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Generator\TwigRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TwigRendererTest extends TestCase
{
    private TwigRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TwigRenderer();
    }

    #[DataProvider('phpExportProvider')]
    public function testPhpExport(mixed $value, string $expected): void
    {
        $result = $this->renderer->phpExport($value);
        $this->assertSame($expected, $result);
    }

    public static function phpExportProvider(): array
    {
        return [
            'boolean true' => [true, 'true'],
            'boolean false' => [false, 'false'],
            'null' => [null, 'null'],
            'integer' => [42, '42'],
            'negative integer' => [-5, '-5'],
            'float' => [3.14, '3.14'],
            'simple string' => ['hello', "'hello'"],
            'string with spaces' => ['hello world', "'hello world'"],
            'string with single quote' => ["it's", "'it\\'s'"],
            'string with backslash' => ['path\\to\\file', "'path\\\\to\\\\file'"],
            'empty string' => ['', "''"],
            'zero' => [0, '0'],
            'zero float' => [0.0, '0'],
        ];
    }

    public function testPhpExportArray(): void
    {
        $result = $this->renderer->phpExport(['a', 'b']);
        $this->assertStringContainsString("'a'", $result);
        $this->assertStringContainsString("'b'", $result);
    }

    public function testUnderscorePrefixedFieldGeneratesCorrectMethodNames(): void
    {
        // Test that underscore-prefixed field names generate camelCase method names
        $this->renderer->set([
            'name' => '_joinData',
            'type' => 'string',
            'nullable' => true,
            'typeHint' => 'string',
            'nullableTypeHint' => '?string',
            'returnTypeHint' => 'string',
            'nullableReturnTypeHint' => '?string',
            'deprecated' => null,
            'collection' => false,
            'collectionType' => null,
        ]);

        // Test getter method name
        $getterOutput = $this->renderer->generate('element/method_get');
        $this->assertStringContainsString('public function getJoinData()', $getterOutput);
        $this->assertStringNotContainsString('get_joinData', $getterOutput);

        // Test has method name
        $hasOutput = $this->renderer->generate('element/method_has');
        $this->assertStringContainsString('public function hasJoinData()', $hasOutput);
        $this->assertStringNotContainsString('has_joinData', $hasOutput);

        // Test setter method name (for mutable DTOs)
        $setterOutput = $this->renderer->generate('element/method_set');
        $this->assertStringContainsString('public function setJoinData(', $setterOutput);
        $this->assertStringNotContainsString('set_joinData', $setterOutput);

        // Test with method name (for immutable DTOs)
        $withOutput = $this->renderer->generate('element/method_with');
        $this->assertStringContainsString('public function withJoinData(', $withOutput);
        $this->assertStringNotContainsString('with_joinData', $withOutput);
    }

    public function testRegularFieldGeneratesCorrectMethodNames(): void
    {
        // Verify regular fields still work correctly
        $this->renderer->set([
            'name' => 'userName',
            'type' => 'string',
            'nullable' => true,
            'typeHint' => 'string',
            'nullableTypeHint' => '?string',
            'returnTypeHint' => 'string',
            'nullableReturnTypeHint' => '?string',
            'deprecated' => null,
            'collection' => false,
            'collectionType' => null,
        ]);

        $getterOutput = $this->renderer->generate('element/method_get');
        $this->assertStringContainsString('public function getUserName()', $getterOutput);
    }
}
