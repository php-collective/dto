<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Importer\Builder;

use PhpCollective\Dto\Importer\Builder\SchemaBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SchemaBuilder.
 */
class SchemaBuilderTest extends TestCase
{
    protected SchemaBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new SchemaBuilder();
    }

    /**
     * @return void
     */
    public function testBuildPhp(): void
    {
        $fields = [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'int'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Dto::create('User')", $output);
        $this->assertStringContainsString("Field::string('name')", $output);
        $this->assertStringContainsString("Field::int('age')", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpWithRequired(): void
    {
        $fields = [
            'name' => ['type' => 'string', 'required' => true],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::string('name')->required()", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpWithSingular(): void
    {
        $fields = [
            'items' => ['type' => 'Item[]', 'singular' => 'item'],
        ];

        $output = $this->builder->build('Order', $fields, ['format' => 'php']);

        $this->assertStringContainsString("->singular('item')", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpWithAssociative(): void
    {
        $fields = [
            'users' => ['type' => 'User[]', 'associative' => 'slug'],
        ];

        $output = $this->builder->build('Group', $fields, ['format' => 'php']);

        $this->assertStringContainsString("->associative('slug')", $output);
    }

    /**
     * @return void
     */
    public function testBuildAllPhp(): void
    {
        $definitions = [
            'User' => [
                'name' => ['type' => 'string'],
            ],
            'Order' => [
                'id' => ['type' => 'int'],
            ],
        ];

        $output = $this->builder->buildAll($definitions, ['format' => 'php']);

        $this->assertStringContainsString('DtoBuilder::create()', $output);
        $this->assertStringContainsString("Dto::create('User')", $output);
        $this->assertStringContainsString("Dto::create('Order')", $output);
    }

    /**
     * @return void
     */
    public function testBuildXml(): void
    {
        $fields = [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'int'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'xml']);

        $this->assertStringContainsString('<dto name="User">', $output);
        $this->assertStringContainsString('name="name" type="string"', $output);
        $this->assertStringContainsString('name="age" type="int"', $output);
    }

    /**
     * @return void
     */
    public function testBuildXmlWithRequired(): void
    {
        $fields = [
            'name' => ['type' => 'string', 'required' => true],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'xml']);

        $this->assertStringContainsString('required="true"', $output);
    }

    /**
     * @return void
     */
    public function testBuildXmlWithAssociative(): void
    {
        $fields = [
            'items' => ['type' => 'Item[]', 'associative' => 'id'],
        ];

        $output = $this->builder->build('Order', $fields, ['format' => 'xml']);

        $this->assertStringContainsString('associative="true"', $output);
        $this->assertStringContainsString('key="id"', $output);
    }

    /**
     * @return void
     */
    public function testBuildAllXml(): void
    {
        $definitions = [
            'User' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $output = $this->builder->buildAll($definitions, ['format' => 'xml']);

        $this->assertStringContainsString('<?xml version="1.0"', $output);
        $this->assertStringContainsString('<dtos xmlns=', $output);
        $this->assertStringContainsString('<dto name="User">', $output);
        $this->assertStringContainsString('</dtos>', $output);
    }

    /**
     * @return void
     */
    public function testBuildYaml(): void
    {
        $fields = [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'int'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'yaml']);

        $this->assertStringContainsString('User:', $output);
        $this->assertStringContainsString('fields:', $output);
        // Shorthand format: fieldName: type (no nested type: key when no options)
        $this->assertStringContainsString('name: string', $output);
        $this->assertStringContainsString('age: int', $output);
    }

    /**
     * @return void
     */
    public function testBuildYamlWithRequired(): void
    {
        $fields = [
            'name' => ['type' => 'string', 'required' => true],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'yaml']);

        $this->assertStringContainsString('required: true', $output);
    }

    /**
     * @return void
     */
    public function testBuildNeon(): void
    {
        $fields = [
            'name' => ['type' => 'string'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'neon']);

        // NEON is similar to YAML - shorthand format
        $this->assertStringContainsString('User:', $output);
        $this->assertStringContainsString('name: string', $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpDtoType(): void
    {
        $fields = [
            'address' => ['type' => 'Address'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::dto('address', 'Address')", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpDtoCollectionType(): void
    {
        $fields = [
            'items' => ['type' => 'Item[]'],
        ];

        $output = $this->builder->build('Order', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::collection('items', 'Item')", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpScalarCollections(): void
    {
        $fields = [
            'tags' => ['type' => 'string[]'],
            'counts' => ['type' => 'int[]'],
        ];

        $output = $this->builder->build('Item', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::array('tags', 'string')", $output);
        $this->assertStringContainsString("Field::array('counts', 'int')", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpNamespacedDtoTypes(): void
    {
        $fields = [
            'user' => ['type' => 'Github/User'],
            'labels' => ['type' => 'Github/Label[]'],
        ];

        $output = $this->builder->build('Issue', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::dto('user', 'Github/User')", $output);
        $this->assertStringContainsString("Field::collection('labels', 'Github/Label')", $output);
    }

    /**
     * @return void
     */
    public function testBuildAllExcludesMarkedDefinitions(): void
    {
        $definitions = [
            'Include' => [
                'name' => ['type' => 'string'],
            ],
            'Exclude' => [
                '_include' => false,
                'name' => ['type' => 'string'],
            ],
        ];

        $output = $this->builder->buildAll($definitions, ['format' => 'php']);

        $this->assertStringContainsString("Dto::create('Include')", $output);
        $this->assertStringNotContainsString("Dto::create('Exclude')", $output);
    }

    /**
     * @return void
     */
    public function testBuildExcludesMarkedFields(): void
    {
        $fields = [
            'include' => ['type' => 'string'],
            'exclude' => ['type' => 'string', '_include' => false],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::string('include')", $output);
        $this->assertStringNotContainsString('exclude', $output);
    }

    /**
     * @return void
     */
    public function testBuildXmlEscapesSpecialCharacters(): void
    {
        $fields = [
            'data' => ['type' => 'string<int>'],
        ];

        $output = $this->builder->build('Test', $fields, ['format' => 'xml']);

        $this->assertStringContainsString('type="string&lt;int&gt;"', $output);
    }

    /**
     * @return void
     */
    public function testDefaultFormatIsPhp(): void
    {
        $fields = [
            'name' => ['type' => 'string'],
        ];

        $output = $this->builder->build('User', $fields);

        $this->assertStringContainsString("Field::string('name')", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpWithExtends(): void
    {
        $fields = [
            '_extends' => 'BaseEntity',
            'email' => ['type' => 'string'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Dto::create('User')->extends('BaseEntity')", $output);
        $this->assertStringContainsString("Field::string('email')", $output);
    }

    /**
     * @return void
     */
    public function testBuildXmlWithExtends(): void
    {
        $fields = [
            '_extends' => 'BaseEntity',
            'email' => ['type' => 'string'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'xml']);

        $this->assertStringContainsString('<dto name="User" extends="BaseEntity">', $output);
    }

    /**
     * @return void
     */
    public function testBuildYamlWithExtends(): void
    {
        $fields = [
            '_extends' => 'BaseEntity',
            'email' => ['type' => 'string'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'yaml']);

        $this->assertStringContainsString('User:', $output);
        $this->assertStringContainsString('extends: BaseEntity', $output);
        $this->assertStringContainsString('email: string', $output);
    }

    /**
     * @return void
     */
    public function testBuildNeonWithExtends(): void
    {
        $fields = [
            '_extends' => 'BaseEntity',
            'email' => ['type' => 'string'],
        ];

        $output = $this->builder->build('User', $fields, ['format' => 'neon']);

        $this->assertStringContainsString('User:', $output);
        $this->assertStringContainsString('extends: BaseEntity', $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpWithEmptyType(): void
    {
        $fields = [
            'data' => ['type' => ''],
        ];

        $output = $this->builder->build('Test', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::mixed('data')", $output);
    }

    /**
     * @return void
     */
    public function testBuildPhpWithEmptyArrayType(): void
    {
        $fields = [
            'items' => ['type' => '[]'],
        ];

        $output = $this->builder->build('Test', $fields, ['format' => 'php']);

        $this->assertStringContainsString("Field::array('items')", $output);
    }
}
