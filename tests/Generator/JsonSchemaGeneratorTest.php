<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\IoInterface;
use PhpCollective\Dto\Generator\JsonSchemaGenerator;
use PHPUnit\Framework\TestCase;

class JsonSchemaGeneratorTest extends TestCase
{
    protected string $tempDir;

    /**
     * @var resource
     */
    protected $stdout;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/json_schema_test_' . uniqid();
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

        $generator = new JsonSchemaGenerator($this->createIo());
        $count = $generator->generate($definitions, $this->tempDir);

        $this->assertSame(1, $count);
        $this->assertFileExists($this->tempDir . '/dto-schemas.json');

        $content = file_get_contents($this->tempDir . '/dto-schemas.json');
        $schema = json_decode($content, true);

        $this->assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
        $this->assertArrayHasKey('$defs', $schema);
        $this->assertArrayHasKey('UserDto', $schema['$defs']);
        $this->assertSame('object', $schema['$defs']['UserDto']['type']);
        $this->assertArrayHasKey('id', $schema['$defs']['UserDto']['properties']);
        $this->assertSame('integer', $schema['$defs']['UserDto']['properties']['id']['type']);
        $this->assertSame('string', $schema['$defs']['UserDto']['properties']['name']['type']);
        $this->assertContains('id', $schema['$defs']['UserDto']['required']);
        $this->assertContains('name', $schema['$defs']['UserDto']['required']);
        $this->assertNotContains('email', $schema['$defs']['UserDto']['required']);
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

        $generator = new JsonSchemaGenerator($this->createIo(), ['singleFile' => false]);
        $count = $generator->generate($definitions, $this->tempDir);

        $this->assertSame(2, $count);
        $this->assertFileExists($this->tempDir . '/UserDto.json');
        $this->assertFileExists($this->tempDir . '/AddressDto.json');

        $userSchema = json_decode(file_get_contents($this->tempDir . '/UserDto.json'), true);
        $this->assertSame('UserDto.json', $userSchema['$id']);
        $this->assertSame('UserDto', $userSchema['title']);
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
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $properties = $schema['$defs']['TestDto']['properties'];

        $this->assertSame('integer', $properties['intField']['type']);
        $this->assertSame('number', $properties['floatField']['type']);
        $this->assertSame('string', $properties['stringField']['type']);
        $this->assertSame('boolean', $properties['boolField']['type']);
        $this->assertSame('array', $properties['arrayField']['type']);
    }

    public function testArrayTypes(): void
    {
        $definitions = [
            'Test' => [
                'name' => 'Test',
                'fields' => [
                    'stringArray' => ['name' => 'stringArray', 'type' => 'string[]', 'required' => true],
                    'intArray' => ['name' => 'intArray', 'type' => 'int[]', 'required' => true],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $properties = $schema['$defs']['TestDto']['properties'];

        $this->assertSame('array', $properties['stringArray']['type']);
        $this->assertSame('string', $properties['stringArray']['items']['type']);

        $this->assertSame('array', $properties['intArray']['type']);
        $this->assertSame('integer', $properties['intArray']['items']['type']);
    }

    public function testNullableTypes(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true, 'nullable' => false],
                    'email' => ['name' => 'email', 'type' => 'string', 'nullable' => true],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $properties = $schema['$defs']['UserDto']['properties'];

        // Non-nullable should just have the type
        $this->assertSame('integer', $properties['id']['type']);

        // Nullable should use oneOf
        $this->assertArrayHasKey('oneOf', $properties['email']);
        $types = array_column($properties['email']['oneOf'], 'type');
        $this->assertContains('string', $types);
        $this->assertContains('null', $types);
    }

    public function testNestedDtoWithRefs(): void
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

        $generator = new JsonSchemaGenerator($this->createIo(), ['useRefs' => true]);
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        $this->assertSame('#/$defs/UserDto', $schema['$defs']['OrderDto']['properties']['customer']['$ref']);
    }

    public function testNestedDtoWithoutRefs(): void
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

        $generator = new JsonSchemaGenerator($this->createIo(), ['useRefs' => false]);
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $customerSchema = $schema['$defs']['OrderDto']['properties']['customer'];

        // Should be inlined, not a $ref
        $this->assertArrayNotHasKey('$ref', $customerSchema);
        $this->assertSame('object', $customerSchema['type']);
        $this->assertArrayHasKey('properties', $customerSchema);
        $this->assertArrayHasKey('id', $customerSchema['properties']);
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
                        'singularType' => 'Item',
                        'singularClass' => '\\App\\Dto\\ItemDto',
                    ],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $itemsSchema = $schema['$defs']['OrderDto']['properties']['items'];

        $this->assertSame('array', $itemsSchema['type']);
        $this->assertSame('#/$defs/ItemDto', $itemsSchema['items']['$ref']);
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

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $properties = $schema['$defs']['EventDto']['properties'];

        $this->assertSame('string', $properties['createdAt']['type']);
        $this->assertSame('date-time', $properties['createdAt']['format']);
    }

    public function testDateFormatOption(): void
    {
        $definitions = [
            'Event' => [
                'name' => 'Event',
                'fields' => [
                    'createdAt' => ['name' => 'createdAt', 'type' => '\\DateTime', 'required' => true],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo(), ['dateFormat' => 'date']);
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        $this->assertSame('date', $schema['$defs']['EventDto']['properties']['createdAt']['format']);
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

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $nestedDir);

        $this->assertDirectoryExists($nestedDir);
        $this->assertFileExists($nestedDir . '/dto-schemas.json');
    }

    public function testAdditionalPropertiesFalse(): void
    {
        $definitions = [
            'User' => [
                'name' => 'User',
                'fields' => [
                    'id' => ['name' => 'id', 'type' => 'int', 'required' => true],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        $this->assertFalse($schema['$defs']['UserDto']['additionalProperties']);
    }

    public function testUnionTypes(): void
    {
        $definitions = [
            'Test' => [
                'name' => 'Test',
                'fields' => [
                    'value' => ['name' => 'value', 'type' => 'int|string', 'required' => true],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $valueSchema = $schema['$defs']['TestDto']['properties']['value'];

        $this->assertArrayHasKey('oneOf', $valueSchema);
        $types = array_column($valueSchema['oneOf'], 'type');
        $this->assertContains('integer', $types);
        $this->assertContains('string', $types);
    }

    public function testMixedTypeAllowsAnything(): void
    {
        $definitions = [
            'Test' => [
                'name' => 'Test',
                'fields' => [
                    'data' => ['name' => 'data', 'type' => 'mixed', 'required' => true],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        // Empty schema allows anything in JSON Schema
        $this->assertEmpty($schema['$defs']['TestDto']['properties']['data']);
    }

    public function testMultiFileWithExternalRefs(): void
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
                    'customer' => [
                        'name' => 'customer',
                        'type' => '\\App\\Dto\\UserDto',
                        'required' => true,
                        'dto' => 'User',
                    ],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo(), ['singleFile' => false]);
        $generator->generate($definitions, $this->tempDir);

        $orderSchema = json_decode(file_get_contents($this->tempDir . '/OrderDto.json'), true);

        // Multi-file mode should use external file refs
        $this->assertSame('UserDto.json', $orderSchema['properties']['customer']['$ref']);
    }

    public function testExtractDtoNameFromDeepNamespace(): void
    {
        $definitions = [
            'Address' => [
                'name' => 'Address',
                'fields' => [
                    'city' => ['name' => 'city', 'type' => 'string', 'required' => true],
                ],
            ],
            'User' => [
                'name' => 'User',
                'fields' => [
                    'address' => [
                        'name' => 'address',
                        'type' => '\\App\\Module\\User\\Dto\\AddressDto',
                        'required' => true,
                        'dto' => 'Address',
                    ],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo(), ['useRefs' => true]);
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        // Should correctly extract 'Address' from deeply nested FQCN
        $this->assertSame('#/$defs/AddressDto', $schema['$defs']['UserDto']['properties']['address']['$ref']);
    }

    public function testExtractDtoNameWithoutSuffix(): void
    {
        $definitions = [
            'Profile' => [
                'name' => 'Profile',
                'fields' => [
                    'bio' => ['name' => 'bio', 'type' => 'string', 'required' => true],
                ],
            ],
            'User' => [
                'name' => 'User',
                'fields' => [
                    'profile' => [
                        'name' => 'profile',
                        'type' => '\\App\\Model\\Profile',
                        'required' => true,
                        'dto' => 'Profile',
                    ],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo(), ['useRefs' => true]);
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        // Should handle type without Dto suffix
        $this->assertSame('#/$defs/ProfileDto', $schema['$defs']['UserDto']['properties']['profile']['$ref']);
    }

    public function testExtractDtoNameForCollectionWithDeepNamespace(): void
    {
        $definitions = [
            'Tag' => [
                'name' => 'Tag',
                'fields' => [
                    'name' => ['name' => 'name', 'type' => 'string', 'required' => true],
                ],
            ],
            'Article' => [
                'name' => 'Article',
                'fields' => [
                    'tags' => [
                        'name' => 'tags',
                        'type' => 'Tag[]',
                        'collection' => true,
                        'singularType' => 'Tag',
                        'singularClass' => '\\App\\Blog\\Content\\Dto\\TagDto',
                    ],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo());
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);
        $tagsSchema = $schema['$defs']['ArticleDto']['properties']['tags'];

        $this->assertSame('array', $tagsSchema['type']);
        $this->assertSame('#/$defs/TagDto', $tagsSchema['items']['$ref']);
    }

    public function testExtractDtoNameWithLeadingBackslash(): void
    {
        $definitions = [
            'Category' => [
                'name' => 'Category',
                'fields' => [
                    'name' => ['name' => 'name', 'type' => 'string', 'required' => true],
                ],
            ],
            'Product' => [
                'name' => 'Product',
                'fields' => [
                    'category' => [
                        'name' => 'category',
                        'type' => '\\CategoryDto',
                        'required' => true,
                        'dto' => 'Category',
                    ],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo(), ['useRefs' => true]);
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        // Should handle FQCN with leading backslash but no namespace
        $this->assertSame('#/$defs/CategoryDto', $schema['$defs']['ProductDto']['properties']['category']['$ref']);
    }

    public function testCustomSuffixExtraction(): void
    {
        $definitions = [
            'Settings' => [
                'name' => 'Settings',
                'fields' => [
                    'theme' => ['name' => 'theme', 'type' => 'string', 'required' => true],
                ],
            ],
            'User' => [
                'name' => 'User',
                'fields' => [
                    'settings' => [
                        'name' => 'settings',
                        'type' => '\\App\\Transfer\\SettingsTransfer',
                        'required' => true,
                        'dto' => 'Settings',
                    ],
                ],
            ],
        ];

        $generator = new JsonSchemaGenerator($this->createIo(), ['suffix' => 'Transfer', 'useRefs' => true]);
        $generator->generate($definitions, $this->tempDir);

        $schema = json_decode(file_get_contents($this->tempDir . '/dto-schemas.json'), true);

        // With custom suffix, definitions should use 'Transfer' suffix
        $this->assertArrayHasKey('SettingsTransfer', $schema['$defs']);
        $this->assertArrayHasKey('UserTransfer', $schema['$defs']);
        $this->assertSame('#/$defs/SettingsTransfer', $schema['$defs']['UserTransfer']['properties']['settings']['$ref']);
    }
}
