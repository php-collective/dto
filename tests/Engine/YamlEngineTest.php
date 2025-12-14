<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Engine;

use InvalidArgumentException;
use PhpCollective\Dto\Engine\YamlEngine;
use PHPUnit\Framework\TestCase;

class YamlEngineTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('yaml_parse')) {
            $this->markTestSkipped('YAML extension not available');
        }
    }

    public function testExtension(): void
    {
        $engine = new YamlEngine();
        $this->assertSame('yml', $engine->extension());
    }

    public function testParse(): void
    {
        $engine = new YamlEngine();
        $yaml = <<<'YAML'
User:
    fields:
        id: int
        name: string
        email: string
YAML;

        $result = $engine->parse($yaml);

        $this->assertArrayHasKey('User', $result);
        $this->assertSame('User', $result['User']['name']);
        $this->assertArrayHasKey('fields', $result['User']);
        $this->assertArrayHasKey('id', $result['User']['fields']);
        $this->assertSame('int', $result['User']['fields']['id']['type']);
    }

    public function testParseWithFullFieldDefinition(): void
    {
        $engine = new YamlEngine();
        $yaml = <<<'YAML'
User:
    fields:
        id:
            type: int
            required: true
        name:
            type: string
            defaultValue: "Guest"
YAML;

        $result = $engine->parse($yaml);

        $this->assertArrayHasKey('User', $result);
        $this->assertTrue($result['User']['fields']['id']['required']);
        $this->assertSame('Guest', $result['User']['fields']['name']['defaultValue']);
    }

    public function testParseMultipleDtos(): void
    {
        $engine = new YamlEngine();
        $yaml = <<<'YAML'
User:
    fields:
        id: int
Article:
    fields:
        title: string
YAML;

        $result = $engine->parse($yaml);

        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('Article', $result);
    }

    public function testParseWithDtoOptions(): void
    {
        $engine = new YamlEngine();
        $yaml = <<<'YAML'
ImmutableUser:
    immutable: true
    fields:
        id: int
YAML;

        $result = $engine->parse($yaml);

        $this->assertArrayHasKey('ImmutableUser', $result);
        $this->assertTrue($result['ImmutableUser']['immutable']);
    }

    public function testParseInvalidYamlThrowsException(): void
    {
        $engine = new YamlEngine();
        $this->expectException(InvalidArgumentException::class);
        $engine->parse('invalid: [not: closed');
    }

    public function testValidateDoesNothing(): void
    {
        $engine = new YamlEngine();
        $engine->validate(['file1.yml', 'file2.yml']);
        $this->assertTrue(true);
    }

    public function testParseUnionTypes(): void
    {
        $engine = new YamlEngine();
        $yaml = <<<'YAML'
Flexible:
    fields:
        id:
            type: int|string
            required: true
        value: int|float|string
YAML;

        $result = $engine->parse($yaml);

        $this->assertSame('int|string', $result['Flexible']['fields']['id']['type']);
        $this->assertSame('int|float|string', $result['Flexible']['fields']['value']['type']);
    }
}
