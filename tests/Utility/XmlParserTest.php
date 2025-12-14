<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Utility;

use PhpCollective\Dto\Utility\XmlParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class XmlParserTest extends TestCase
{
    public function testBuild(): void
    {
        $xml = '<?xml version="1.0"?><root><item>value</item></root>';
        $element = XmlParser::build($xml);
        $this->assertSame('root', $element->getName());
        $this->assertSame('value', (string)$element->item);
    }

    public function testBuildThrowsExceptionOnInvalidXml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('XML parsing failed');
        XmlParser::build('<invalid>');
    }

    public function testToArray(): void
    {
        $xml = '<?xml version="1.0"?><root><item attr="test">value</item></root>';
        $element = XmlParser::build($xml);
        $result = XmlParser::toArray($element);

        $this->assertArrayHasKey('root', $result);
        $this->assertArrayHasKey('item', $result['root']);
        $this->assertSame('test', $result['root']['item']['@attr']);
        $this->assertSame('value', $result['root']['item']['@value']);
    }

    public function testToArrayWithMultipleChildren(): void
    {
        $xml = '<?xml version="1.0"?><root><item>one</item><item>two</item></root>';
        $element = XmlParser::build($xml);
        $result = XmlParser::toArray($element);

        $this->assertArrayHasKey('root', $result);
        $this->assertIsArray($result['root']['item']);
        $this->assertCount(2, $result['root']['item']);
        $this->assertSame('one', $result['root']['item'][0]);
        $this->assertSame('two', $result['root']['item'][1]);
    }

    public function testToArrayWithNestedElements(): void
    {
        $xml = '<?xml version="1.0"?><dtos><dto name="User"><field name="id" type="int"/></dto></dtos>';
        $element = XmlParser::build($xml);
        $result = XmlParser::toArray($element);

        $this->assertArrayHasKey('dtos', $result);
        $this->assertArrayHasKey('dto', $result['dtos']);
        $this->assertSame('User', $result['dtos']['dto']['@name']);
    }

    public function testToArrayWithEmptyElement(): void
    {
        $xml = '<?xml version="1.0"?><root><empty/></root>';
        $element = XmlParser::build($xml);
        $result = XmlParser::toArray($element);

        $this->assertArrayHasKey('root', $result);
        $this->assertSame('', $result['root']['empty']);
    }
}
