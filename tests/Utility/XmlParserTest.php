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

    /**
     * Test that XXE (XML External Entity) attacks are blocked.
     *
     * @return void
     */
    public function testXxeProtection(): void
    {
        // This XXE payload attempts to read /etc/passwd via an external entity
        // With proper protection (LIBXML_NONET), the entity should not be resolved
        $xxePayload = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE root [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<root><item>&xxe;</item></root>
XML;

        // The parser should either:
        // 1. Not resolve the entity (return empty or literal &xxe;)
        // 2. Throw an exception
        // It should NOT return actual file contents

        try {
            $element = XmlParser::build($xxePayload);
            $result = XmlParser::toArray($element);

            // If we get here, the entity was not resolved (which is correct)
            // The value should be empty or the literal entity reference
            $value = $result['root']['item'] ?? '';
            $this->assertStringNotContainsString('root:', (string)$value, 'XXE attack should not resolve /etc/passwd');
        } catch (RuntimeException $e) {
            // Parser rejecting the XML is also acceptable
            $this->assertTrue(true);
        }
    }
}
