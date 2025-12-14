<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Engine;

use InvalidArgumentException;
use PhpCollective\Dto\Engine\NeonEngine;
use PHPUnit\Framework\TestCase;

class NeonEngineTest extends TestCase
{
    public function testExtension(): void
    {
        $engine = new NeonEngine();
        $this->assertSame('neon', $engine->extension());
    }

    public function testParse(): void
    {
        $engine = new NeonEngine();
        $neon = <<<'NEON'
User:
    fields:
        id: int
        name: string
        email: string
NEON;

        $result = $engine->parse($neon);

        $this->assertArrayHasKey('User', $result);
        $this->assertSame('User', $result['User']['name']);
        $this->assertArrayHasKey('fields', $result['User']);
        $this->assertArrayHasKey('id', $result['User']['fields']);
        $this->assertSame('int', $result['User']['fields']['id']['type']);
    }

    public function testParseWithFullFieldDefinition(): void
    {
        $engine = new NeonEngine();
        $neon = <<<'NEON'
User:
    fields:
        id:
            type: int
            required: true
        name:
            type: string
            defaultValue: "Guest"
NEON;

        $result = $engine->parse($neon);

        $this->assertArrayHasKey('User', $result);
        $this->assertTrue($result['User']['fields']['id']['required']);
        $this->assertSame('Guest', $result['User']['fields']['name']['defaultValue']);
    }

    public function testParseMultipleDtos(): void
    {
        $engine = new NeonEngine();
        $neon = <<<'NEON'
User:
    fields:
        id: int
Article:
    fields:
        title: string
NEON;

        $result = $engine->parse($neon);

        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('Article', $result);
    }

    public function testParseWithDtoOptions(): void
    {
        $engine = new NeonEngine();
        $neon = <<<'NEON'
ImmutableUser:
    immutable: true
    fields:
        id: int
NEON;

        $result = $engine->parse($neon);

        $this->assertArrayHasKey('ImmutableUser', $result);
        $this->assertTrue($result['ImmutableUser']['immutable']);
    }

    public function testParseInvalidNeonThrowsException(): void
    {
        $engine = new NeonEngine();
        $this->expectException(InvalidArgumentException::class);
        $engine->parse('invalid: [not: closed');
    }

    public function testValidateDoesNothing(): void
    {
        $engine = new NeonEngine();
        $engine->validate(['file1.neon', 'file2.neon']);
        $this->assertTrue(true);
    }
}
