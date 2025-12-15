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
}
