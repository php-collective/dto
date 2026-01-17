<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Engine\XmlEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\TwigRenderer;
use PHPUnit\Framework\TestCase;

class DefaultValueRenderingTest extends TestCase
{
    public function testBooleanDefaultValueRenderedAsTrue(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="BoolDefault">
        <field name="enabled" type="bool" defaultValue="true"/>
        <field name="disabled" type="bool" defaultValue="false"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'BoolDefault');

        // Default values in setDefaults() should use 'true' not '1'
        $this->assertStringContainsString('$this->enabled = true;', $code);
        $this->assertStringContainsString('$this->disabled = false;', $code);
        $this->assertStringNotContainsString('$this->enabled = 1;', $code);
        $this->assertStringNotContainsString('$this->disabled = 0;', $code);
    }

    public function testStringDefaultValueRenderedWithQuotes(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="StringDefault">
        <field name="country" type="string" defaultValue="USA"/>
        <field name="status" type="string" defaultValue="active"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'StringDefault');

        // Default values in setDefaults() should have quoted strings
        $this->assertStringContainsString("\$this->country = 'USA';", $code);
        $this->assertStringContainsString("\$this->status = 'active';", $code);
    }

    public function testIntegerDefaultValueRenderedWithoutQuotes(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="IntDefault">
        <field name="count" type="int" defaultValue="10"/>
        <field name="priority" type="int" defaultValue="0"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'IntDefault');

        // Default values in setDefaults() should have unquoted integers
        $this->assertStringContainsString('$this->count = 10;', $code);
        $this->assertStringContainsString('$this->priority = 0;', $code);
    }

    public function testFloatDefaultValueRenderedCorrectly(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="FloatDefault">
        <field name="rate" type="float" defaultValue="3.14"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'FloatDefault');

        // Default value in setDefaults() should be unquoted float
        $this->assertStringContainsString('$this->rate = 3.14;', $code);
    }

    public function testCollectionPropertyInitializedToNull(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="CollectionInit">
        <field name="id" type="int"/>
        <field name="items" type="Item[]" collection="true" singular="item"/>
    </dto>
    <dto name="Item">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'CollectionInit');

        // Collection property should be nullable and initialized to null
        $this->assertStringContainsString('?\ArrayObject $items = null;', $code);
    }

    public function testDefaultValueInSetDefaults(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="SetDefaults">
        <field name="active" type="bool" defaultValue="true"/>
        <field name="name" type="string" defaultValue="default"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'SetDefaults');

        // setDefaults method should use proper PHP values
        $this->assertStringContainsString('$this->active = true;', $code);
        $this->assertStringContainsString("\$this->name = 'default';", $code);
    }

    private function generateDtoCode(string $xml, string $dtoName): string
    {
        $engine = new XmlEngine();
        $config = new ArrayConfig([
            'namespace' => 'Test',
        ]);

        $builder = new Builder($engine, $config);
        $renderer = new TwigRenderer(null, $config);

        // Create temp directory and file with proper name
        $tmpDir = sys_get_temp_dir() . '/dto_test_' . uniqid() . '/';
        mkdir($tmpDir);
        $tmpFile = $tmpDir . 'dto.xml';
        file_put_contents($tmpFile, $xml);

        try {
            $dtos = $builder->build($tmpDir);
            $this->assertArrayHasKey($dtoName, $dtos);

            $renderer->set($dtos[$dtoName]);

            return $renderer->generate('dto');
        } finally {
            @unlink($tmpFile);
            @rmdir($tmpDir);
        }
    }
}
