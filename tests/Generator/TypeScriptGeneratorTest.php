<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\IoInterface;
use PhpCollective\Dto\Generator\TypeScriptGenerator;
use PHPUnit\Framework\TestCase;

class TypeScriptGeneratorTest extends TestCase
{
    protected string $tempDir;

    /**
     * @var resource
     */
    protected $stdout;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ts_generator_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
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

    public function testGenerateSingleFile(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                    'name' => ['name' => 'name', 'type' => 'string', 'required' => true],
                    'email' => ['name' => 'email', 'type' => 'string'],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $count = $generator->generate($definitions, $this->tempDir);

        $this->assertSame(1, $count);
        $this->assertFileExists($this->tempDir . '/dto.ts');

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('export interface UserDto', $content);
        $this->assertStringContainsString('id: number;', $content);
        $this->assertStringContainsString('name: string;', $content);
        $this->assertStringContainsString('email?: string;', $content);
    }

    public function testGenerateMultipleFiles(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
            'Address' => [
                'name' => 'Address',
                'fields' => [
                    'street' => ['name' => 'street', 'type' => 'string', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo(), ['singleFile' => false]);
        $count = $generator->generate($definitions, $this->tempDir);

        $this->assertSame(3, $count); // 2 DTOs + index
        $this->assertFileExists($this->tempDir . '/UserDto.ts');
        $this->assertFileExists($this->tempDir . '/AddressDto.ts');
        $this->assertFileExists($this->tempDir . '/index.ts');
    }

    public function testGenerateWithDashedFileNames(): void
    {
        $definitions = [
            'OrderItem' => [
                'name' => 'OrderItem',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo(), [
            'singleFile' => false,
            'fileNameCase' => 'dashed',
        ]);
        $generator->generate($definitions, $this->tempDir);

        $this->assertFileExists($this->tempDir . '/order-item-dto.ts');
    }

    public function testGenerateWithSnakeFileNames(): void
    {
        $definitions = [
            'OrderItem' => [
                'name' => 'OrderItem',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo(), [
            'singleFile' => false,
            'fileNameCase' => 'snake',
        ]);
        $generator->generate($definitions, $this->tempDir);

        $this->assertFileExists($this->tempDir . '/order_item_dto.ts');
    }

    public function testTypeMapping(): void
    {
        $definitions = [
            'Test' => [
                'name' => 'Test',
                'fields' => [
                    'intField' => ['name' => 'intField', 'type' => 'int', 'required' => true],
                    'floatField' => ['name' => 'floatField', 'type' => 'float', 'required' => true],
                    'stringField' => ['name' => 'stringField', 'type' => 'string', 'required' => true],
                    'boolField' => ['name' => 'boolField', 'type' => 'bool', 'required' => true],
                    'arrayField' => ['name' => 'arrayField', 'type' => 'array', 'required' => true],
                    'mixedField' => ['name' => 'mixedField', 'type' => 'mixed', 'required' => true],
                    'stringArray' => ['name' => 'stringArray', 'type' => 'string[]', 'required' => true],
                    'intArray' => ['name' => 'intArray', 'type' => 'int[]', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('intField: number;', $content);
        $this->assertStringContainsString('floatField: number;', $content);
        $this->assertStringContainsString('stringField: string;', $content);
        $this->assertStringContainsString('boolField: boolean;', $content);
        $this->assertStringContainsString('arrayField: any[];', $content);
        $this->assertStringContainsString('mixedField: unknown;', $content);
        $this->assertStringContainsString('stringArray: string[];', $content);
        $this->assertStringContainsString('intArray: number[];', $content);
    }

    public function testReadonlyOption(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo(), ['readonly' => true]);
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('readonly id: number;', $content);
    }

    public function testImmutableDtoGetsReadonly(): void
    {
        $definitions = [
            'ImmutableUser' => [
                'name' => 'ImmutableUser',
                'immutable' => true,
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('readonly id: number;', $content);
    }

    public function testStrictNullsOption(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                    'email' => ['name' => 'email', 'type' => 'string'],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo(), ['strictNulls' => true]);
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('id: number;', $content);
        $this->assertStringContainsString('email: string | null;', $content);
    }

    public function testNestedDtoReferences(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
            'Order' => [
                'name' => 'Order',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                    'customer' => [
                        'name' => 'customer',
                        'type' => '\\App\\Dto\\UserDto',
                        'required' => true,
                        'dto' => 'User',
                    ],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('customer: UserDto;', $content);
    }

    public function testMultiFileWithImports(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
            'Order' => [
                'name' => 'Order',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                    'customer' => [
                        'name' => 'customer',
                        'type' => '\\App\\Dto\\UserDto',
                        'required' => true,
                        'dto' => 'User',
                    ],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo(), [
            'singleFile' => false,
            'fileNameCase' => 'dashed',
        ]);
        $generator->generate($definitions, $this->tempDir);

        $orderContent = file_get_contents($this->tempDir . '/order-dto.ts');
        $this->assertStringContainsString("import type { UserDto } from './user-dto';", $orderContent);
    }

    public function testIndexFileExportsAll(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
            'Address' => [
                'name' => 'Address',
                'fields' => [
                    'street' => ['name' => 'street', 'type' => 'string', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo(), ['singleFile' => false]);
        $generator->generate($definitions, $this->tempDir);

        $indexContent = file_get_contents($this->tempDir . '/index.ts');
        $this->assertStringContainsString("export * from './UserDto';", $indexContent);
        $this->assertStringContainsString("export * from './AddressDto';", $indexContent);
    }

    public function testCollectionTypes(): void
    {
        $definitions = [
            'Item' => [
                'name' => 'Item',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
            'Order' => [
                'name' => 'Order',
                'fields' => [
                    'items' => [
                        'name' => 'items',
                        'type' => 'Item[]',
                        'collection' => true,
                        'singularClass' => '\\App\\Dto\\ItemDto',
                    ],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('items?: ItemDto[];', $content);
    }

    public function testDateTimeMapping(): void
    {
        $definitions = [
            'Event' => [
                'name' => 'Event',
                'fields' => [
                    'createdAt' => ['name' => 'createdAt', 'type' => '\\DateTime', 'required' => true],
                    'updatedAt' => ['name' => 'updatedAt', 'type' => '\\DateTimeImmutable'],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $content = file_get_contents($this->tempDir . '/dto.ts');
        $this->assertStringContainsString('createdAt: string;', $content);
        $this->assertStringContainsString('updatedAt?: string;', $content);
    }

    public function testCreatesOutputDirectory(): void
    {
        $nestedDir = $this->tempDir . '/nested/output/path';

        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
        ];

        $generator = new TypeScriptGenerator($this->createIo());
        $generator->generate($definitions, $nestedDir);

        $this->assertDirectoryExists($nestedDir);
        $this->assertFileExists($nestedDir . '/dto.ts');
    }
}
