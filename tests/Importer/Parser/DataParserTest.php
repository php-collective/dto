<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Importer\Parser;

use PhpCollective\Dto\Importer\Parser\DataParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DataParser.
 */
class DataParserTest extends TestCase
{
    protected DataParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DataParser();
    }

    /**
     * @return void
     */
    public function testParseName(): void
    {
        $this->assertSame('Data', DataParser::NAME);
    }

    /**
     * @return void
     */
    public function testParseScalarTypes(): void
    {
        $data = [
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
        ];

        $result = $this->parser->parse($data)->result();

        $this->assertSame('string', $result['Object']['string']['type']);
        $this->assertSame('int', $result['Object']['int']['type']);
        $this->assertSame('float', $result['Object']['float']['type']);
        $this->assertSame('bool', $result['Object']['bool']['type']);
    }

    /**
     * @return void
     */
    public function testParseNestedObject(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ];

        $result = $this->parser->parse($data)->result();

        $this->assertArrayHasKey('Object', $result);
        $this->assertArrayHasKey('User', $result);
        $this->assertSame('User', $result['Object']['user']['type']);
        $this->assertSame('string', $result['User']['name']['type']);
    }

    /**
     * @return void
     */
    public function testParseCollection(): void
    {
        $data = [
            'items' => [
                ['id' => 1, 'name' => 'Item 1'],
                ['id' => 2, 'name' => 'Item 2'],
            ],
        ];

        $result = $this->parser->parse($data)->result();

        $this->assertSame('Item[]', $result['Object']['items']['type']);
        $this->assertSame('item', $result['Object']['items']['singular']);
        $this->assertSame('string', $result['Item']['name']['type']);
    }

    /**
     * @return void
     */
    public function testDetectAssociativeKey(): void
    {
        $data = [
            'users' => [
                ['slug' => 'john', 'name' => 'John'],
                ['slug' => 'jane', 'name' => 'Jane'],
            ],
        ];

        $result = $this->parser->parse($data)->result();

        $this->assertSame('slug', $result['Object']['users']['associative']);
    }

    /**
     * @return void
     */
    public function testParseWithNamespace(): void
    {
        $data = ['name' => 'John'];

        $result = $this->parser->parse($data, ['namespace' => 'App/Dto'])->result();

        $this->assertArrayHasKey('App/Dto/Object', $result);
    }

    /**
     * @return void
     */
    public function testParseNestedWithNamespace(): void
    {
        $data = [
            'address' => [
                'city' => 'NYC',
            ],
        ];

        $result = $this->parser->parse($data, ['namespace' => 'App'])->result();

        $this->assertArrayHasKey('App/Object', $result);
        $this->assertArrayHasKey('App/Address', $result);
        $this->assertSame('App/Address', $result['App/Object']['address']['type']);
    }

    /**
     * @return void
     */
    public function testSkipPrivateFields(): void
    {
        $data = [
            'name' => 'John',
            '_private' => 'secret',
            '__meta' => 'hidden',
        ];

        $result = $this->parser->parse($data)->result();

        $this->assertArrayHasKey('name', $result['Object']);
        $this->assertArrayNotHasKey('_private', $result['Object']);
        $this->assertArrayNotHasKey('__meta', $result['Object']);
    }

    /**
     * @return void
     */
    public function testNullValueType(): void
    {
        $data = ['value' => null];

        $result = $this->parser->parse($data)->result();

        $this->assertSame('mixed', $result['Object']['value']['type']);
    }

    /**
     * @return void
     */
    public function testSimpleArrayNotTreatedAsCollection(): void
    {
        $data = [
            'tags' => ['php', 'dto', 'generator'],
        ];

        $result = $this->parser->parse($data)->result();

        // Simple string array should be 'array', not a collection
        $this->assertSame('array', $result['Object']['tags']['type']);
    }

    /**
     * @return void
     */
    public function testDeepNesting(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ];

        $result = $this->parser->parse($data)->result();

        $this->assertArrayHasKey('Object', $result);
        $this->assertArrayHasKey('Level1', $result);
        $this->assertArrayHasKey('Level2', $result);
        $this->assertArrayHasKey('Level3', $result);
    }
}
