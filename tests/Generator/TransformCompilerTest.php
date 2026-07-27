<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Generator\TransformCompiler;
use PhpCollective\Dto\Test\TestDto\TransformHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransformCompilerTest extends TestCase
{
    #[DataProvider('compileProvider')]
    public function testCompile(?string $callable, string $expr, bool $guardNull, string $expected): void
    {
        $this->assertSame($expected, TransformCompiler::compile($callable, $expr, $guardNull, true));
    }

    public function testCompileWithoutStrictTypesFallsBack(): void
    {
        $callable = TransformHelper::class . '::normalizeEmail';
        $expected = '$this->transformValue(\'' . str_replace('\\', '\\\\', $callable) . '\', $value)';

        $this->assertSame($expected, TransformCompiler::compile($callable, '$value', false, false));
    }

    /**
     * @return array<string, array{0: string|null, 1: string, 2: bool, 3: string}>
     */
    public static function compileProvider(): array
    {
        $helper = TransformHelper::class;

        return [
            'null callable' => [null, '$value', false, '$value'],
            'empty callable' => ['', '$value', false, '$value'],
            'plain function' => ['trim', '$value', false, '\trim($value)'],
            'static method' => [
                $helper . '::normalizeEmail',
                '$value',
                false,
                '\\' . $helper . '::normalizeEmail($value)',
            ],
            'leading backslash' => [
                '\\' . $helper . '::normalizeEmail',
                '$value',
                false,
                '\\' . $helper . '::normalizeEmail($value)',
            ],
            'guard null true' => [
                $helper . '::normalizeEmail',
                '$value',
                true,
                '($value === null ? null : \\' . $helper . '::normalizeEmail($value))',
            ],
            'guard null false with non-simple expression' => [
                $helper . '::normalizeEmail',
                '$this->value()',
                false,
                '\\' . $helper . '::normalizeEmail($this->value())',
            ],
            'guard null true with non-simple expression falls back' => [
                $helper . '::normalizeEmail',
                '$this->value()',
                true,
                '$this->transformValue(\'' . str_replace('\\', '\\\\', $helper) . '::normalizeEmail\', $this->value())',
            ],
            'unresolvable callable falls back' => [
                'App\\Transform\\Email::normalizeTypo',
                '$value',
                false,
                '$this->transformValue(\'App\\\\Transform\\\\Email::normalizeTypo\', $value)',
            ],
            'unresolvable function falls back' => [
                'trimx',
                '$value',
                false,
                '$this->transformValue(\'trimx\', $value)',
            ],
            'injection-shaped function call' => [
                "foo(); echo 'x'",
                '$value',
                false,
                '$this->transformValue(\'foo(); echo \\\'x\\\'\', $value)',
            ],
            'injection-shaped static method' => [
                'Foo::bar; //',
                '$value',
                false,
                '$this->transformValue(\'Foo::bar; //\', $value)',
            ],
            'trailing whitespace' => ['trim ', '$value', false, '$this->transformValue(\'trim \', $value)'],
            'fallback escapes backslashes and quotes' => [
                "Foo\\Bar::baz'qux",
                '$value',
                false,
                '$this->transformValue(\'Foo\\\\Bar::baz\\\'qux\', $value)',
            ],
        ];
    }
}
