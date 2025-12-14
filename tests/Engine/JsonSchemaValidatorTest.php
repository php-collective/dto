<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Engine;

use InvalidArgumentException;
use PhpCollective\Dto\Engine\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

class JsonSchemaValidatorTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        if (!JsonSchemaValidator::isAvailable()) {
            $this->markTestSkipped('justinrainbow/json-schema not installed');
        }

        $this->tempDir = sys_get_temp_dir() . '/json-schema-validator-test-' . uniqid();
        mkdir($this->tempDir);
        // Reset schema path to default
        JsonSchemaValidator::setSchemaPath(dirname(__DIR__, 2) . '/config/dto.schema.json');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->tempDir);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue(JsonSchemaValidator::isAvailable());
    }

    public function testValidateValidData(): void
    {
        $data = [
            'User' => [
                'fields' => [
                    'id' => 'int',
                    'name' => 'string',
                ],
            ],
        ];

        // Should not throw
        JsonSchemaValidator::validate($data, 'test.yml');
        $this->assertTrue(true);
    }

    public function testValidateValidDataWithFullFieldDefinition(): void
    {
        $data = [
            'User' => [
                'fields' => [
                    'id' => [
                        'type' => 'int',
                        'required' => true,
                    ],
                    'name' => [
                        'type' => 'string',
                        'defaultValue' => 'Guest',
                    ],
                ],
            ],
        ];

        // Should not throw
        JsonSchemaValidator::validate($data, 'test.yml');
        $this->assertTrue(true);
    }

    public function testValidateWithAllFieldAttributes(): void
    {
        $data = [
            'ComplexDto' => [
                'extends' => 'BaseDto',
                'immutable' => true,
                'fields' => [
                    'items' => [
                        'type' => 'Item[]',
                        'collection' => true,
                        'singular' => 'item',
                        'associative' => true,
                        'key' => 'id',
                    ],
                    'count' => [
                        'type' => 'int',
                        'required' => true,
                        'defaultValue' => 0,
                    ],
                    'oldField' => [
                        'type' => 'string',
                        'deprecated' => 'Use newField instead',
                    ],
                    'custom' => [
                        'type' => 'MyClass',
                        'factory' => 'MyFactory::create',
                    ],
                ],
            ],
        ];

        // Should not throw
        JsonSchemaValidator::validate($data, 'test.yml');
        $this->assertTrue(true);
    }

    public function testValidateInvalidDataUnknownFieldProperty(): void
    {
        $data = [
            'User' => [
                'fields' => [
                    'id' => [
                        'type' => 'int',
                        'unknownProperty' => true,
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknownProperty');
        JsonSchemaValidator::validate($data, 'test.yml');
    }

    public function testValidateInvalidDataUnknownDtoProperty(): void
    {
        $data = [
            'User' => [
                'unknownDtoProperty' => true,
                'fields' => [
                    'id' => 'int',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknownDtoProperty');
        JsonSchemaValidator::validate($data, 'test.yml');
    }

    public function testValidateFieldMissingType(): void
    {
        $data = [
            'User' => [
                'fields' => [
                    'id' => [
                        'required' => true,
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('type');
        JsonSchemaValidator::validate($data, 'test.yml');
    }

    public function testGetSchemaPathDefault(): void
    {
        JsonSchemaValidator::setSchemaPath(dirname(__DIR__, 2) . '/config/dto.schema.json');
        $path = JsonSchemaValidator::getSchemaPath();

        $this->assertStringEndsWith('dto.schema.json', $path);
        $this->assertFileExists($path);
    }

    public function testSetSchemaPath(): void
    {
        $customPath = '/custom/path/schema.json';
        JsonSchemaValidator::setSchemaPath($customPath);

        $this->assertSame($customPath, JsonSchemaValidator::getSchemaPath());
    }

    public function testValidateEmptyDtos(): void
    {
        $data = [];

        // Should not throw - empty DTOs is valid
        JsonSchemaValidator::validate($data, 'test.yml');
        $this->assertTrue(true);
    }

    public function testValidateDeprecatedDto(): void
    {
        $data = [
            'OldDto' => [
                'deprecated' => 'Use NewDto instead',
                'fields' => [
                    'id' => 'int',
                ],
            ],
        ];

        // Should not throw
        JsonSchemaValidator::validate($data, 'test.yml');
        $this->assertTrue(true);
    }
}
