<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Engine;

use InvalidArgumentException;
use PhpCollective\Dto\Engine\JsonSchemaValidator;
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

    public function testValidateSkipsWhenSchemaValidatorNotAvailable(): void
    {
        if (JsonSchemaValidator::isAvailable()) {
            $this->markTestSkipped('Test requires json-schema to NOT be installed');
        }

        $engine = new NeonEngine();
        // Should not throw even with non-existent files when validator not available
        $engine->validate(['nonexistent.neon']);
        $this->assertTrue(true);
    }

    public function testValidateValidFile(): void
    {
        if (!JsonSchemaValidator::isAvailable()) {
            $this->markTestSkipped('justinrainbow/json-schema not installed');
        }

        $tempDir = sys_get_temp_dir() . '/neon-engine-test-' . uniqid();
        mkdir($tempDir);

        $neon = <<<'NEON'
User:
    fields:
        id: int
        name: string
NEON;
        $path = $tempDir . '/valid.neon';
        file_put_contents($path, $neon);

        $engine = new NeonEngine();
        $engine->validate([$path]);
        $this->assertTrue(true);

        unlink($path);
        rmdir($tempDir);
    }

    public function testValidateInvalidFileThrowsException(): void
    {
        if (!JsonSchemaValidator::isAvailable()) {
            $this->markTestSkipped('justinrainbow/json-schema not installed');
        }

        $tempDir = sys_get_temp_dir() . '/neon-engine-test-' . uniqid();
        mkdir($tempDir);

        $neon = <<<'NEON'
User:
    unknownProperty: true
    fields:
        id: int
NEON;
        $path = $tempDir . '/invalid.neon';
        file_put_contents($path, $neon);

        $engine = new NeonEngine();

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('unknownProperty');
            $engine->validate([$path]);
        } finally {
            unlink($path);
            rmdir($tempDir);
        }
    }

    public function testValidateUnreadableFileThrowsException(): void
    {
        if (!JsonSchemaValidator::isAvailable()) {
            $this->markTestSkipped('justinrainbow/json-schema not installed');
        }

        $engine = new NeonEngine();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot read file');
        $engine->validate(['/nonexistent/file.neon']);
    }

    public function testParseUnionTypes(): void
    {
        $engine = new NeonEngine();
        $neon = <<<'NEON'
Flexible:
    fields:
        id:
            type: int|string
            required: true
        value: int|float|string
NEON;

        $result = $engine->parse($neon);

        $this->assertSame('int|string', $result['Flexible']['fields']['id']['type']);
        $this->assertSame('int|float|string', $result['Flexible']['fields']['value']['type']);
    }
}
