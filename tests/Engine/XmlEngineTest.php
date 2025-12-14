<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Engine;

use PhpCollective\Dto\Engine\XmlEngine;
use PHPUnit\Framework\TestCase;

class XmlEngineTest extends TestCase
{
    public function testExtension(): void
    {
        $engine = new XmlEngine();
        $this->assertSame('xml', $engine->extension());
    }

    public function testParse(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos>
    <dto name="User">
        <field name="id" type="int" required="true"/>
        <field name="name" type="string"/>
        <field name="email" type="string"/>
    </dto>
</dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertArrayHasKey('User', $result);
        $this->assertSame('User', $result['User']['name']);
        $this->assertArrayHasKey('fields', $result['User']);
        $this->assertArrayHasKey('id', $result['User']['fields']);
        $this->assertSame('int', $result['User']['fields']['id']['type']);
        $this->assertTrue($result['User']['fields']['id']['required']);
    }

    public function testParseMultipleDtos(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos>
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
    <dto name="Article">
        <field name="title" type="string"/>
    </dto>
</dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('Article', $result);
    }

    public function testParseWithDtoAttributes(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos>
    <dto name="ImmutableUser" immutable="true">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertArrayHasKey('ImmutableUser', $result);
        $this->assertTrue($result['ImmutableUser']['immutable']);
    }

    public function testParseEmptyDtos(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos></dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertSame([], $result);
    }

    public function testParseWithDefaultValues(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos>
    <dto name="Settings">
        <field name="count" type="int" defaultValue="10"/>
        <field name="ratio" type="float" defaultValue="1.5"/>
        <field name="enabled" type="bool" defaultValue="true"/>
    </dto>
</dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertSame(10, $result['Settings']['fields']['count']['defaultValue']);
        $this->assertSame(1.5, $result['Settings']['fields']['ratio']['defaultValue']);
        $this->assertTrue($result['Settings']['fields']['enabled']['defaultValue']);
    }

    public function testParseWithAssociativeCollection(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos>
    <dto name="Container">
        <field name="items" type="Item[]" associative="true"/>
    </dto>
</dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertTrue($result['Container']['fields']['items']['associative']);
    }

    public function testParseWithCollectionKey(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos>
    <dto name="Container">
        <field name="items" type="Item[]" collection="true"/>
    </dto>
</dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertTrue($result['Container']['fields']['items']['collection']);
    }

    public function testParseUnionTypes(): void
    {
        $engine = new XmlEngine();
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos>
    <dto name="Flexible">
        <field name="id" type="int|string" required="true"/>
        <field name="value" type="int|float|string"/>
    </dto>
</dtos>
XML;

        $result = $engine->parse($xml);

        $this->assertSame('int|string', $result['Flexible']['fields']['id']['type']);
        $this->assertSame('int|float|string', $result['Flexible']['fields']['value']['type']);
    }
}
