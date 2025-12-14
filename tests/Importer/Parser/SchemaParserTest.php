<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Importer\Parser;

use PhpCollective\Dto\Importer\Parser\SchemaParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SchemaParser.
 */
class SchemaParserTest extends TestCase
{
    protected SchemaParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SchemaParser();
    }

    /**
     * @return void
     */
    public function testParseName(): void
    {
        $this->assertSame('Schema', SchemaParser::NAME);
    }

    /**
     * @return void
     */
    public function testParseBasicSchema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'active' => ['type' => 'boolean'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('string', $result['Object']['name']['type']);
        $this->assertSame('int', $result['Object']['age']['type']);
        $this->assertSame('bool', $result['Object']['active']['type']);
    }

    /**
     * @return void
     */
    public function testParseWithTitle(): void
    {
        $schema = [
            'type' => 'object',
            'title' => 'User Profile',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('UserProfile', $result);
    }

    /**
     * @return void
     */
    public function testParseRequiredFields(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'required' => ['id'],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertTrue($result['Object']['id']['required']);
        $this->assertFalse($result['Object']['name']['required']);
    }

    /**
     * @return void
     */
    public function testParseNestedObject(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => ['type' => 'string'],
                        'zip' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('Address', $result['Object']['address']['type']);
        $this->assertSame('string', $result['Address']['city']['type']);
    }

    /**
     * @return void
     */
    public function testParseArrayOfObjects(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('Item[]', $result['Object']['items']['type']);
        $this->assertArrayHasKey('Item', $result);
    }

    /**
     * @return void
     */
    public function testParseNullableType(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => ['string', 'null']],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('string', $result['Object']['value']['type']);
        $this->assertFalse($result['Object']['value']['required']);
    }

    /**
     * @return void
     */
    public function testParseAnyOf(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'null'],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('string', $result['Object']['data']['type']);
    }

    /**
     * @return void
     */
    public function testParseOneOf(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'oneOf' => [
                        ['type' => 'string'],
                        ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('string|int', $result['Object']['data']['type']);
    }

    /**
     * @return void
     */
    public function testParseEnum(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'enum' => ['active', 'inactive', 'pending'],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('string', $result['Object']['status']['type']);
    }

    /**
     * @return void
     */
    public function testParseNumberType(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'price' => ['type' => 'number'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('float', $result['Object']['price']['type']);
    }

    /**
     * @return void
     */
    public function testSkipRefProperties(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'related' => ['$ref' => '#/definitions/Other'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('name', $result['Object']);
        $this->assertArrayNotHasKey('related', $result['Object']);
    }

    /**
     * @return void
     */
    public function testParseWithNamespace(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $result = $this->parser->parse($schema, ['namespace' => 'Api/V1'])->result();

        $this->assertArrayHasKey('Api/V1/Object', $result);
    }

    /**
     * @return void
     */
    public function testParseEmptySchema(): void
    {
        $result = $this->parser->parse([])->result();
        $this->assertEmpty($result);
    }

    /**
     * @return void
     */
    public function testParseSchemaWithoutProperties(): void
    {
        $schema = ['type' => 'object'];

        $result = $this->parser->parse($schema)->result();

        $this->assertEmpty($result);
    }

    /**
     * @return void
     */
    public function testParseMixedType(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'data' => [],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('mixed', $result['Object']['data']['type']);
    }

    /**
     * @return void
     */
    public function testSkipUnderscoreProperties(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                '_internal' => ['type' => 'string'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('name', $result['Object']);
        $this->assertArrayNotHasKey('_internal', $result['Object']);
    }
}
