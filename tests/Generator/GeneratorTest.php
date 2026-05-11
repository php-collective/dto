<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use InvalidArgumentException;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\Generator;
use PhpCollective\Dto\Generator\IoInterface;
use PhpCollective\Dto\Generator\RendererInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for Generator class.
 */
class GeneratorTest extends TestCase
{
    /**
     * Test that DTO names with path traversal sequences are rejected.
     *
     * @return void
     */
    public function testPathTraversalInDtoNameThrowsException(): void
    {
        $builder = $this->createMock(Builder::class);
        $renderer = $this->createMock(RendererInterface::class);
        $io = $this->createMock(IoInterface::class);
        $config = new ArrayConfig([]);

        // Builder returns a DTO with a malicious name containing path traversal
        $builder->method('build')->willReturn([
            '../../../etc/malicious' => [
                'name' => '../../../etc/malicious',
                'fields' => [],
            ],
        ]);

        $renderer->method('generate')->willReturn('<?php // malicious');

        $generator = new Generator($builder, $renderer, $io, $config);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('path traversal not allowed');

        $tempDir = sys_get_temp_dir() . '/dto_path_test_' . uniqid() . '/';
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . 'Dto', 0777, true);

        try {
            $generator->generate($tempDir, $tempDir);
        } finally {
            @rmdir($tempDir . 'Dto');
            @rmdir($tempDir);
        }
    }

    /**
     * Regression: `findExistingDtos()` matched `#src/Dto/(.+)<suffix>\.php$#`
     * which hardcoded forward slashes. On Windows the RecursiveDirectoryIterator
     * yields paths with backslashes and the regex never matched — so the
     * "delete DTOs no longer in the spec" pass silently no-op'd, leaving stale
     * generated files behind. The replacement pattern accepts either
     * separator and normalizes captured nested paths to `/` so the same
     * nested entry isn't double-counted across OSes.
     *
     * Exercising the Windows path directly on a Linux CI box is awkward, so
     * the now-DS-tolerant regex is driven via a temp-dir round trip on the
     * host OS path layout and the test additionally asserts that nested-
     * directory entries get normalized to the canonical forward-slash form.
     *
     * @return void
     */
    public function testFindExistingDtosToleratesEitherDirectorySeparator(): void
    {
        $generator = new Generator(
            $this->createMock(Builder::class),
            $this->createMock(RendererInterface::class),
            $this->createMock(IoInterface::class),
            new ArrayConfig([]),
        );
        $method = new ReflectionMethod(Generator::class, 'findExistingDtos');

        $dir = sys_get_temp_dir() . '/dto-find-existing-' . uniqid();
        mkdir($dir . '/src/Dto/Nested', 0777, true);
        file_put_contents($dir . '/src/Dto/FooDto.php', '<?php');
        file_put_contents($dir . '/src/Dto/Nested/BarDto.php', '<?php');

        try {
            $result = $method->invoke($generator, $dir . '/src/Dto');
            $keys = array_keys($result);
            sort($keys);
            $this->assertSame(['Foo', 'Nested/Bar'], $keys);
            $this->assertArrayHasKey('Nested/Bar', $result);
        } finally {
            @unlink($dir . '/src/Dto/FooDto.php');
            @unlink($dir . '/src/Dto/Nested/BarDto.php');
            @rmdir($dir . '/src/Dto/Nested');
            @rmdir($dir . '/src/Dto');
            @rmdir($dir . '/src');
            @rmdir($dir);
        }
    }

    /**
     * Mirror regression for `findExistingMappers()` — same forward-slash
     * hardcode in `#Mapper/(.+)<suffix>Mapper\.php$#`, same fix.
     *
     * @return void
     */
    public function testFindExistingMappersToleratesEitherDirectorySeparator(): void
    {
        $generator = new Generator(
            $this->createMock(Builder::class),
            $this->createMock(RendererInterface::class),
            $this->createMock(IoInterface::class),
            new ArrayConfig([]),
        );
        $method = new ReflectionMethod(Generator::class, 'findExistingMappers');

        $dir = sys_get_temp_dir() . '/dto-find-mappers-' . uniqid();
        mkdir($dir . '/src/Dto/Mapper/Nested', 0777, true);
        file_put_contents($dir . '/src/Dto/Mapper/FooDtoMapper.php', '<?php');
        file_put_contents($dir . '/src/Dto/Mapper/Nested/BarDtoMapper.php', '<?php');

        try {
            $result = $method->invoke($generator, $dir . '/src/Dto/Mapper');
            $keys = array_keys($result);
            sort($keys);
            $this->assertSame(['Foo', 'Nested/Bar'], $keys);
            $this->assertArrayHasKey('Nested/Bar', $result);
        } finally {
            @unlink($dir . '/src/Dto/Mapper/FooDtoMapper.php');
            @unlink($dir . '/src/Dto/Mapper/Nested/BarDtoMapper.php');
            @rmdir($dir . '/src/Dto/Mapper/Nested');
            @rmdir($dir . '/src/Dto/Mapper');
            @rmdir($dir . '/src/Dto');
            @rmdir($dir . '/src');
            @rmdir($dir);
        }
    }
}
