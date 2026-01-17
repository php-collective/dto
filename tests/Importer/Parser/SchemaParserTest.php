<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Importer\Parser;

use PhpCollective\Dto\Importer\Parser\SchemaParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
    public function testResolveRefWithDefs(): void
    {
        $schema = [
            'type' => 'object',
            'title' => 'Customer',
            '$defs' => [
                'Address' => [
                    'type' => 'object',
                    'properties' => [
                        'street' => ['type' => 'string'],
                        'city' => ['type' => 'string'],
                    ],
                ],
            ],
            'properties' => [
                'name' => ['type' => 'string'],
                'address' => ['$ref' => '#/$defs/Address'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('name', $result['Customer']);
        $this->assertArrayHasKey('address', $result['Customer']);
        $this->assertSame('Address', $result['Customer']['address']['type']);
        $this->assertArrayHasKey('Address', $result);
        $this->assertSame('string', $result['Address']['street']['type']);
    }

    /**
     * @return void
     */
    public function testResolveRefWithDefinitions(): void
    {
        $schema = [
            'type' => 'object',
            'definitions' => [
                'Profile' => [
                    'type' => 'object',
                    'properties' => [
                        'bio' => ['type' => 'string'],
                    ],
                ],
            ],
            'properties' => [
                'profile' => ['$ref' => '#/definitions/Profile'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('Profile', $result['Object']['profile']['type']);
        $this->assertArrayHasKey('Profile', $result);
    }

    /**
     * @return void
     */
    public function testResolveRefWithOpenApiComponents(): void
    {
        $schema = [
            'type' => 'object',
            'title' => 'Order',
            'components' => [
                'schemas' => [
                    'LineItem' => [
                        'type' => 'object',
                        'properties' => [
                            'productId' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'properties' => [
                'item' => ['$ref' => '#/components/schemas/LineItem'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('LineItem', $result['Order']['item']['type']);
        $this->assertArrayHasKey('LineItem', $result);
    }

    /**
     * @return void
     */
    public function testResolveRefInArrayItems(): void
    {
        $schema = [
            'type' => 'object',
            'title' => 'Order',
            '$defs' => [
                'LineItem' => [
                    'type' => 'object',
                    'properties' => [
                        'productId' => ['type' => 'integer'],
                        'price' => ['type' => 'number'],
                    ],
                ],
            ],
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/$defs/LineItem'],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('LineItem[]', $result['Order']['items']['type']);
        $this->assertArrayHasKey('LineItem', $result);
        $this->assertSame('int', $result['LineItem']['productId']['type']);
    }

    /**
     * @return void
     */
    public function testResolveRefDeduplication(): void
    {
        $schema = [
            'type' => 'object',
            'title' => 'Customer',
            '$defs' => [
                'Address' => [
                    'type' => 'object',
                    'properties' => [
                        'street' => ['type' => 'string'],
                    ],
                ],
            ],
            'properties' => [
                'billingAddress' => ['$ref' => '#/$defs/Address'],
                'shippingAddress' => ['$ref' => '#/$defs/Address'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        // Both fields should reference the same DTO type
        $this->assertSame('Address', $result['Customer']['billingAddress']['type']);
        $this->assertSame('Address', $result['Customer']['shippingAddress']['type']);
        // Address DTO should only be created once
        $this->assertCount(2, $result); // Customer + Address
    }

    /**
     * @return void
     */
    public function testSkipUnresolvableRef(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'related' => ['$ref' => '#/definitions/NonExistent'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('name', $result['Object']);
        $this->assertArrayNotHasKey('related', $result['Object']);
    }

    /**
     * @return void
     */
    public function testSkipExternalRef(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'external' => ['$ref' => 'other-file.json#/definitions/Other'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('name', $result['Object']);
        $this->assertArrayNotHasKey('external', $result['Object']);
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

    /**
     * @return void
     */
    public function testParseOpenApiDocument(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API', 'version' => '1.0.0'],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'email' => ['type' => 'string'],
                        ],
                        'required' => ['id'],
                    ],
                    'Product' => [
                        'type' => 'object',
                        'properties' => [
                            'sku' => ['type' => 'string'],
                            'price' => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($openapi)->result();

        // Both schemas should be parsed
        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('Product', $result);

        // Check User fields
        $this->assertSame('int', $result['User']['id']['type']);
        $this->assertTrue($result['User']['id']['required']);
        $this->assertSame('string', $result['User']['email']['type']);

        // Check Product fields
        $this->assertSame('string', $result['Product']['sku']['type']);
        $this->assertSame('float', $result['Product']['price']['type']);
    }

    /**
     * @return void
     */
    public function testParseOpenApiWithCrossReferences(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'components' => [
                'schemas' => [
                    'Author' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                    'Book' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'author' => ['$ref' => '#/components/schemas/Author'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($openapi)->result();

        $this->assertArrayHasKey('Author', $result);
        $this->assertArrayHasKey('Book', $result);
        $this->assertSame('Author', $result['Book']['author']['type']);
    }

    /**
     * @return void
     */
    public function testParseOpenApiSkipsNonObjectSchemas(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                    'Status' => [
                        'type' => 'string',
                        'enum' => ['active', 'inactive'],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($openapi)->result();

        // Only object schemas should be parsed
        $this->assertArrayHasKey('User', $result);
        $this->assertArrayNotHasKey('Status', $result);
    }

    /**
     * @return void
     */
    public function testParseOpenApiWithNamespace(): void
    {
        $openapi = [
            'openapi' => '3.0.0',
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($openapi, ['namespace' => 'Api'])->result();

        $this->assertArrayHasKey('Api/User', $result);
    }

    /**
     * @return void
     */
    public function testFormatDateTime(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('\\DateTimeInterface', $result['Object']['createdAt']['type']);
    }

    /**
     * @return void
     */
    public function testFormatDate(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'birthDate' => ['type' => 'string', 'format' => 'date'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('\\DateTimeInterface', $result['Object']['birthDate']['type']);
    }

    /**
     * @return void
     */
    public function testFormatEmailRemainsString(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'format' => 'email'],
                'website' => ['type' => 'string', 'format' => 'uri'],
                'id' => ['type' => 'string', 'format' => 'uuid'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        // These formats don't change the type - they're just validation hints
        $this->assertSame('string', $result['Object']['email']['type']);
        $this->assertSame('string', $result['Object']['website']['type']);
        $this->assertSame('string', $result['Object']['id']['type']);
    }

    /**
     * @return void
     */
    public function testFormatOnlyAppliestoStringType(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                // Format on non-string type should be ignored
                'timestamp' => ['type' => 'integer', 'format' => 'int64'],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('int', $result['Object']['timestamp']['type']);
    }

    /**
     * @return void
     */
    public function testParseAllOfWithRef(): void
    {
        $schema = [
            '$defs' => [
                'BaseEntity' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'createdAt' => ['type' => 'string'],
                    ],
                ],
            ],
            'type' => 'object',
            'title' => 'User',
            'allOf' => [
                ['$ref' => '#/$defs/BaseEntity'],
                [
                    'type' => 'object',
                    'properties' => [
                        'email' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['email'],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        // User should exist with its own properties
        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('email', $result['User']);
        $this->assertArrayHasKey('name', $result['User']);
        $this->assertTrue($result['User']['email']['required']);

        // User should extend BaseEntity
        $this->assertArrayHasKey('_extends', $result['User']);
        $this->assertSame('BaseEntity', $result['User']['_extends']);

        // BaseEntity should also be parsed as a separate DTO
        $this->assertArrayHasKey('BaseEntity', $result);
        $this->assertArrayHasKey('id', $result['BaseEntity']);
    }

    /**
     * @return void
     */
    public function testParseAllOfMergesProperties(): void
    {
        $schema = [
            'type' => 'object',
            'title' => 'Combined',
            'allOf' => [
                [
                    'type' => 'object',
                    'properties' => [
                        'first' => ['type' => 'string'],
                    ],
                ],
                [
                    'type' => 'object',
                    'properties' => [
                        'second' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('Combined', $result);
        $this->assertArrayHasKey('first', $result['Combined']);
        $this->assertArrayHasKey('second', $result['Combined']);
        $this->assertSame('string', $result['Combined']['first']['type']);
        $this->assertSame('int', $result['Combined']['second']['type']);
    }

    /**
     * @return void
     */
    public function testParseAllOfMergesRequiredFields(): void
    {
        $schema = [
            'type' => 'object',
            'allOf' => [
                [
                    'type' => 'object',
                    'properties' => [
                        'a' => ['type' => 'string'],
                    ],
                    'required' => ['a'],
                ],
                [
                    'type' => 'object',
                    'properties' => [
                        'b' => ['type' => 'string'],
                    ],
                    'required' => ['b'],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertTrue($result['Object']['a']['required']);
        $this->assertTrue($result['Object']['b']['required']);
    }

    /**
     * @return void
     */
    public function testParseAllOfWithoutRef(): void
    {
        $schema = [
            'type' => 'object',
            'title' => 'Simple',
            'allOf' => [
                [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertArrayHasKey('Simple', $result);
        $this->assertArrayHasKey('name', $result['Simple']);
        // No _extends since there was no $ref
        $this->assertArrayNotHasKey('_extends', $result['Simple']);
    }

    /**
     * @return void
     */
    public function testParseAllOfInheritanceOnly(): void
    {
        $schema = [
            '$defs' => [
                'BaseEntity' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                    ],
                ],
            ],
            'type' => 'object',
            'title' => 'Empty',
            'allOf' => [
                ['$ref' => '#/$defs/BaseEntity'],
                // No additional properties - only inheritance
            ],
        ];

        $result = $this->parser->parse($schema)->result();

        // Empty should exist even with no own properties
        $this->assertArrayHasKey('Empty', $result);
        $this->assertArrayHasKey('_extends', $result['Empty']);
        $this->assertSame('BaseEntity', $result['Empty']['_extends']);
        // BaseEntity should also be parsed
        $this->assertArrayHasKey('BaseEntity', $result);
    }

    /**
     * @return void
     */
    public function testParseArrayUnionWithNull(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => ['type' => ['array', 'null']],
            ],
            'required' => ['items'],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('array', $result['Object']['items']['type']);
        // Should be optional because null is in union
        $this->assertFalse($result['Object']['items']['required']);
    }

    /**
     * @return void
     */
    public function testParseArrayUnionWithoutNull(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => ['type' => ['array', 'object']],
            ],
            'required' => ['items'],
        ];

        $result = $this->parser->parse($schema)->result();

        $this->assertSame('array', $result['Object']['items']['type']);
        // Should remain required because null is not in union
        $this->assertTrue($result['Object']['items']['required']);
    }

    /**
     * Test that deeply nested schemas throw an exception to prevent stack overflow.
     *
     * @return void
     */
    public function testRecursionDepthLimitThrowsException(): void
    {
        // Build a schema that exceeds MAX_DEPTH (50) by creating deeply nested objects
        // Each nested object with type=object and properties triggers a recursive parse() call
        // Use unique field names to prevent deduplication
        $deepestLevel = [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string'],
            ],
        ];

        // Build from bottom up: wrap in 55 levels of nesting (exceeds MAX_DEPTH of 50)
        $current = $deepestLevel;
        for ($i = 0; $i < 55; $i++) {
            $current = [
                'type' => 'object',
                'properties' => [
                    'level' . $i => $current,
                ],
            ];
        }

        $schema = $current;
        $schema['title'] = 'DeeplyNested';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maximum schema nesting depth exceeded');

        $this->parser->parse($schema)->result();
    }
}
