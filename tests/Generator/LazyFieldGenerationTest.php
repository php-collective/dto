<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Engine\XmlEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\TwigRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for lazy field code generation.
 */
class LazyFieldGenerationTest extends TestCase
{
    public function testSetFromArrayFastUsesArrayKeyExistsForLazyFields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazySetFrom">
        <field name="nested" type="NestedDto" lazy="true"/>
    </dto>
    <dto name="NestedDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazySetFrom');

        // setFromArrayFast should use array_key_exists for lazy fields
        $this->assertStringContainsString("array_key_exists('nested', \$data)", $code);
        $this->assertStringContainsString("\$this->_lazyData['nested']", $code);
    }

    public function testSetDefaultsChecksLazyDataForLazyFieldsWithDefaults(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyDefault">
        <field name="config" type="string" lazy="true" defaultValue="default"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyDefault');

        // setDefaults should check _lazyData for lazy fields
        $this->assertStringContainsString("!array_key_exists('config', \$this->_lazyData)", $code);
    }

    public function testValidateChecksLazyDataForRequiredLazyFields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyRequired">
        <field name="data" type="DataDto" lazy="true" required="true"/>
    </dto>
    <dto name="DataDto">
        <field name="value" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyRequired');

        // validate should check _lazyData for required lazy fields
        $this->assertStringContainsString("!array_key_exists('data', \$this->_lazyData)", $code);
    }

    public function testGetOrFailCallsGetter(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyGetOrFail">
        <field name="nested" type="NestedDto" lazy="true"/>
    </dto>
    <dto name="NestedDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyGetOrFail');

        // getOrFail should call the getter (which handles lazy hydration)
        $this->assertStringContainsString('$value = $this->getNested();', $code);
        $this->assertStringContainsString('if ($value === null)', $code);
    }

    public function testSetOrFailClearsLazyData(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazySetOrFail">
        <field name="nested" type="NestedDto" lazy="true"/>
    </dto>
    <dto name="NestedDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazySetOrFail');

        // setOrFail should clear _lazyData for lazy fields
        $this->assertStringContainsString('function setNestedOrFail', $code);
        $this->assertStringContainsString("unset(\$this->_lazyData['nested'])", $code);
    }

    public function testWithOrFailClearsLazyData(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyWithOrFail" immutable="true">
        <field name="nested" type="NestedDto" lazy="true"/>
    </dto>
    <dto name="NestedDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyWithOrFail');

        // withOrFail should clear _lazyData for lazy fields
        $this->assertStringContainsString('function withNestedOrFail', $code);
        $this->assertStringContainsString("unset(\$new->_lazyData['nested'])", $code);
    }

    public function testGetterUsesArrayKeyExistsForLazyFields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyGetter">
        <field name="nested" type="NestedDto" lazy="true"/>
    </dto>
    <dto name="NestedDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyGetter');

        // getter should use array_key_exists to detect null values
        $this->assertStringContainsString("array_key_exists('nested', \$this->_lazyData)", $code);
    }

    public function testAddMethodHydratesLazyCollectionFirst(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyAdd">
        <field name="items" type="ItemDto[]" lazy="true" singular="item"/>
    </dto>
    <dto name="ItemDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyAdd');

        // addItem should call getter first to hydrate the collection
        $this->assertStringContainsString('function addItem', $code);
        $this->assertStringContainsString('$this->getItems()', $code);

        // Extract addItem method to verify it doesn't directly unset _lazyData
        preg_match('/function addItem\([^)]*\)\s*\{([^}]+)\}/s', $code, $matches);
        $this->assertNotEmpty($matches, 'addItem method should exist');
        $addItemBody = $matches[1];
        $this->assertStringNotContainsString('unset($this->_lazyData', $addItemBody);
    }

    public function testRemoveMethodHydratesLazyCollectionFirst(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyRemove">
        <field name="items" type="ItemDto[]" lazy="true" singular="item"/>
    </dto>
    <dto name="ItemDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyRemove');

        // removeItem should call getter first to hydrate the collection
        $this->assertStringContainsString('function removeItem', $code);
        $this->assertStringContainsString('$this->getItems()', $code);
    }

    public function testWithAddedMethodHydratesLazyCollectionFirst(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyWithAdded" immutable="true">
        <field name="items" type="ItemDto[]" lazy="true" singular="item"/>
    </dto>
    <dto name="ItemDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyWithAdded');

        // withAddedItem should call getter first to hydrate the collection on clone
        $this->assertStringContainsString('function withAddedItem', $code);
        $this->assertStringContainsString('$new->getItems()', $code);
    }

    public function testWithRemovedMethodHydratesLazyCollectionFirst(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="LazyWithRemoved" immutable="true">
        <field name="items" type="ItemDto[]" lazy="true" singular="item"/>
    </dto>
    <dto name="ItemDto">
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateDtoCode($xml, 'LazyWithRemoved');

        // withRemovedItem should call getter first to hydrate the collection on clone
        $this->assertStringContainsString('function withRemovedItem', $code);
        $this->assertStringContainsString('$new->getItems()', $code);
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
