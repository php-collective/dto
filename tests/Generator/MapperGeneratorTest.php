<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Engine\XmlEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\TwigRenderer;
use PHPUnit\Framework\TestCase;

class MapperGeneratorTest extends TestCase
{
    public function testMapperExtendsDto(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateMapperCode($xml, 'User');

        $this->assertStringContainsString('class UserDtoMapper extends UserDto', $code);
        $this->assertStringContainsString('use Test\Dto\UserDto;', $code);
    }

    public function testMapperHasPositionalConstructor(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
        <field name="email" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateMapperCode($xml, 'User');

        // Check constructor has positional parameters
        $this->assertStringContainsString('public function __construct(', $code);
        $this->assertStringContainsString('int $id,', $code);
        $this->assertStringContainsString('string $name,', $code);
        $this->assertStringContainsString('string $email', $code);
    }

    public function testMapperCallsParentWithArray(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateMapperCode($xml, 'User');

        // Check parent constructor call with array
        $this->assertStringContainsString('parent::__construct([', $code);
        $this->assertStringContainsString("'id' => \$id,", $code);
        $this->assertStringContainsString("'name' => \$name,", $code);
    }

    public function testMapperHandlesNullableTypes(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="phone" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateMapperCode($xml, 'User');

        // nullable field should have ? prefix
        $this->assertStringContainsString('?string $phone', $code);
    }

    public function testMapperNamespace(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;

        $code = $this->generateMapperCode($xml, 'User');

        $this->assertStringContainsString('namespace Test\Dto\Mapper;', $code);
    }

    public function testMapperHasDoctrineDocBlock(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;

        $code = $this->generateMapperCode($xml, 'User');

        $this->assertStringContainsString('Doctrine-compatible mapper', $code);
        $this->assertStringContainsString('SELECT NEW', $code);
    }

    public function testMapperSyntaxIsValid(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="Order">
        <field name="id" type="int"/>
        <field name="total" type="float"/>
        <field name="status" type="string"/>
        <field name="notes" type="string"/>
    </dto>
</dtos>
XML;

        $code = $this->generateMapperCode($xml, 'Order');

        // Write to temp file and validate syntax
        $tmpFile = sys_get_temp_dir() . '/mapper_test_' . uniqid() . '.php';
        file_put_contents($tmpFile, $code);

        try {
            exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $returnCode);
            $this->assertSame(0, $returnCode, 'PHP syntax error: ' . implode("\n", $output));
        } finally {
            @unlink($tmpFile);
        }
    }

    private function generateMapperCode(string $xml, string $dtoName): string
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

            return $renderer->generate('mapper');
        } finally {
            @unlink($tmpFile);
            @rmdir($tmpDir);
        }
    }
}
