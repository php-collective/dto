<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Utility;

use PhpCollective\Dto\Utility\Json;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JsonTest extends TestCase
{
    public function testEncode(): void
    {
        $json = new Json();
        $data = ['foo' => 'bar', 'baz' => 123];
        $result = $json->encode($data);
        $this->assertSame('{"foo":"bar","baz":123}', $result);
    }

    public function testEncodeThrowsExceptionOnInvalidData(): void
    {
        $json = new Json();
        $resource = fopen('php://memory', 'r');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON encoding failed');
        $json->encode($resource);
        fclose($resource);
    }

    public function testDecode(): void
    {
        $json = new Json();
        $jsonString = '{"foo":"bar","baz":123}';
        $result = $json->decode($jsonString);
        $this->assertSame(['foo' => 'bar', 'baz' => 123], $result);
    }

    public function testDecodeAsObject(): void
    {
        $json = new Json();
        $jsonString = '{"foo":"bar","baz":123}';
        $result = $json->decode($jsonString, false);
        $this->assertIsObject($result);
        $this->assertSame('bar', $result->foo);
        $this->assertSame(123, $result->baz);
    }

    public function testDecodeThrowsExceptionOnInvalidJson(): void
    {
        $json = new Json();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON decoding failed');
        $json->decode('{"invalid json');
    }
}
