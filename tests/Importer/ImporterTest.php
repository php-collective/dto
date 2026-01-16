<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Importer;

use PhpCollective\Dto\Importer\Importer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the main Importer class.
 */
class ImporterTest extends TestCase
{
    protected Importer $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new Importer();
    }

    /**
     * @return void
     */
    public function testParseSimpleJsonData(): void
    {
        $json = '{"name": "John", "age": 30, "active": true}';
        $result = $this->importer->parse($json);

        $this->assertArrayHasKey('Object', $result);
        $this->assertArrayHasKey('name', $result['Object']);
        $this->assertArrayHasKey('age', $result['Object']);
        $this->assertArrayHasKey('active', $result['Object']);

        $this->assertSame('string', $result['Object']['name']['type']);
        $this->assertSame('int', $result['Object']['age']['type']);
        $this->assertSame('bool', $result['Object']['active']['type']);
    }

    /**
     * @return void
     */
    public function testParseNestedJsonData(): void
    {
        $json = '{"name": "John", "address": {"city": "NYC", "zip": "10001"}}';
        $result = $this->importer->parse($json);

        $this->assertArrayHasKey('Object', $result);
        $this->assertArrayHasKey('Address', $result);

        $this->assertSame('Address', $result['Object']['address']['type']);
        $this->assertSame('string', $result['Address']['city']['type']);
    }

    /**
     * @return void
     */
    public function testParseCollectionData(): void
    {
        $json = '{"users": [{"name": "John"}, {"name": "Jane"}]}';
        $result = $this->importer->parse($json);

        $this->assertArrayHasKey('Object', $result);
        $this->assertArrayHasKey('User', $result);

        $this->assertSame('User[]', $result['Object']['users']['type']);
        $this->assertSame('user', $result['Object']['users']['singular']);
    }

    /**
     * @return void
     */
    public function testParseJsonSchema(): void
    {
        $schema = json_encode([
            'type' => 'object',
            'title' => 'User',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ]);

        $result = $this->importer->parse($schema);

        $this->assertArrayHasKey('User', $result);
        $this->assertTrue($result['User']['name']['required']);
        $this->assertFalse($result['User']['age']['required']);
        $this->assertSame('string', $result['User']['name']['type']);
        $this->assertSame('int', $result['User']['age']['type']);
    }

    /**
     * @return void
     */
    public function testParseWithNamespace(): void
    {
        $json = '{"name": "John"}';
        $result = $this->importer->parse($json, ['namespace' => 'Api/Response']);

        $this->assertArrayHasKey('Api/Response/Object', $result);
    }

    /**
     * @return void
     */
    public function testParseArray(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $result = $this->importer->parseArray($data);

        $this->assertArrayHasKey('Object', $result);
        $this->assertSame('string', $result['Object']['name']['type']);
    }

    /**
     * @return void
     */
    public function testBuildSchemaPhp(): void
    {
        $json = '{"name": "John", "age": 30}';
        $definitions = $this->importer->parse($json);
        $output = $this->importer->buildSchema($definitions, ['format' => 'php']);

        $this->assertStringContainsString("Field::string('name')", $output);
        $this->assertStringContainsString("Field::int('age')", $output);
        $this->assertStringContainsString("Dto::create('Object')", $output);
    }

    /**
     * @return void
     */
    public function testBuildSchemaXml(): void
    {
        $json = '{"name": "John", "age": 30}';
        $definitions = $this->importer->parse($json);
        $output = $this->importer->buildSchema($definitions, ['format' => 'xml']);

        $this->assertStringContainsString('<dto name="Object">', $output);
        $this->assertStringContainsString('name="name" type="string"', $output);
        $this->assertStringContainsString('name="age" type="int"', $output);
    }

    /**
     * @return void
     */
    public function testBuildSchemaYaml(): void
    {
        $json = '{"name": "John", "age": 30}';
        $definitions = $this->importer->parse($json);
        $output = $this->importer->buildSchema($definitions, ['format' => 'yaml']);

        $this->assertStringContainsString('Object:', $output);
        // Shorthand format: fieldName: type (no nested type: key when no options)
        $this->assertStringContainsString('name: string', $output);
        $this->assertStringContainsString('age: int', $output);
    }

    /**
     * @return void
     */
    public function testImportConvenienceMethod(): void
    {
        $json = '{"name": "John"}';
        $output = $this->importer->import($json, ['format' => 'php']);

        $this->assertStringContainsString("Field::string('name')", $output);
    }

    /**
     * @return void
     */
    public function testImportArrayConvenienceMethod(): void
    {
        $data = ['name' => 'John'];
        $output = $this->importer->importArray($data, ['format' => 'php']);

        $this->assertStringContainsString("Field::string('name')", $output);
    }

    /**
     * @return void
     */
    public function testParseEmptyJson(): void
    {
        $result = $this->importer->parse('{}');
        $this->assertEmpty($result);
    }

    /**
     * @return void
     */
    public function testParseNullValue(): void
    {
        $json = '{"value": null}';
        $result = $this->importer->parse($json);

        $this->assertSame('mixed', $result['Object']['value']['type']);
    }

    /**
     * @return void
     */
    public function testParseFloatValue(): void
    {
        $json = '{"price": 19.99}';
        $result = $this->importer->parse($json);

        $this->assertSame('float', $result['Object']['price']['type']);
    }

    /**
     * @return void
     */
    public function testAutoDetectJsonSchema(): void
    {
        // This should be auto-detected as JSON Schema
        $schema = json_encode([
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
        ]);

        $result = $this->importer->parse($schema);

        // Schema parser marks required explicitly
        $this->assertArrayHasKey('Object', $result);
        $this->assertArrayHasKey('required', $result['Object']['id']);
    }

    /**
     * @return void
     */
    public function testAutoDetectDataJson(): void
    {
        // This should be auto-detected as plain data
        $json = '{"id": 123, "type": "user"}';
        $result = $this->importer->parse($json);

        // Data parser doesn't have required field
        $this->assertArrayHasKey('Object', $result);
        $this->assertArrayNotHasKey('required', $result['Object']['id']);
    }

    /**
     * @return void
     */
    public function testSkipUnderscoreFields(): void
    {
        $json = '{"name": "John", "_internal": "secret"}';
        $result = $this->importer->parse($json);

        $this->assertArrayHasKey('name', $result['Object']);
        $this->assertArrayNotHasKey('_internal', $result['Object']);
    }

    /**
     * @return void
     */
    public function testFieldNameNormalization(): void
    {
        $json = '{"first_name": "John", "last-name": "Doe", "UserEmail": "john@example.com"}';
        $result = $this->importer->parse($json);

        $this->assertArrayHasKey('firstName', $result['Object']);
        $this->assertArrayHasKey('lastName', $result['Object']);
        $this->assertArrayHasKey('userEmail', $result['Object']);
    }

    /**
     * @return void
     */
    public function testParseOpenApiDocument(): void
    {
        $openapi = json_encode([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                    'Profile' => [
                        'type' => 'object',
                        'properties' => [
                            'bio' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->importer->parse($openapi);

        // Both schemas should be parsed
        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('Profile', $result);
        $this->assertSame('int', $result['User']['id']['type']);
        $this->assertSame('string', $result['Profile']['bio']['type']);
    }

    /**
     * @return void
     */
    public function testParseOpenApiWithRefs(): void
    {
        $openapi = json_encode([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'profile' => ['$ref' => '#/components/schemas/Profile'],
                        ],
                    ],
                    'Profile' => [
                        'type' => 'object',
                        'properties' => [
                            'bio' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->importer->parse($openapi);

        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('Profile', $result);
        $this->assertSame('Profile', $result['User']['profile']['type']);
    }

    /**
     * @return void
     */
    public function testParseOpenApiWithCollectionRef(): void
    {
        $openapi = json_encode([
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'components' => [
                'schemas' => [
                    'Order' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'items' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/LineItem'],
                            ],
                        ],
                    ],
                    'LineItem' => [
                        'type' => 'object',
                        'properties' => [
                            'productId' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->importer->parse($openapi);

        $this->assertArrayHasKey('Order', $result);
        $this->assertArrayHasKey('LineItem', $result);
        $this->assertSame('LineItem[]', $result['Order']['items']['type']);
    }

    /**
     * @return void
     */
    public function testAutoDetectOpenApi(): void
    {
        // OpenAPI documents should be auto-detected
        $openapi = [
            'openapi' => '3.0.0',
            'components' => [
                'schemas' => [
                    'Test' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->importer->parseArray($openapi);

        $this->assertArrayHasKey('Test', $result);
        $this->assertArrayNotHasKey('Object', $result);
    }
}
