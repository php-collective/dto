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
}
