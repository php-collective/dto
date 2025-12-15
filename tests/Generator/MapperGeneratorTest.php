<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Engine\XmlEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\Generator;
use PhpCollective\Dto\Generator\IoInterface;
use PhpCollective\Dto\Generator\TwigRenderer;
use PHPUnit\Framework\TestCase;

class MapperGeneratorTest extends TestCase
{
    protected string $tempDir;

    protected string $configDir;

    protected string $srcDir;

    /**
     * @var resource
     */
    protected $stdout;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mapper_test_' . uniqid();
        $this->configDir = $this->tempDir . '/config/';
        $this->srcDir = $this->tempDir . '/src/';
        mkdir($this->configDir, 0777, true);
        mkdir($this->srcDir, 0777, true);
        $this->stdout = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        fclose($this->stdout);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function createIo(): ConsoleIo
    {
        return new ConsoleIo(IoInterface::QUIET, $this->stdout, $this->stdout);
    }

    protected function writeConfig(string $xml): void
    {
        file_put_contents($this->configDir . 'dto.xml', $xml);
    }

    // ===========================================
    // Template Rendering Tests
    // ===========================================

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

    // ===========================================
    // Generator Integration Tests
    // ===========================================

    public function testGeneratorCreatesMapperFiles(): void
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
        $this->writeConfig($xml);

        $generator = $this->createGenerator();
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true]);

        // Check DTO file exists
        $this->assertFileExists($this->srcDir . 'Dto/UserDto.php');

        // Check Mapper file exists
        $this->assertFileExists($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');

        // Verify mapper content
        $mapperContent = file_get_contents($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');
        $this->assertStringContainsString('class UserDtoMapper extends UserDto', $mapperContent);
    }

    public function testGeneratorCreatesMultipleMappers(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
    <dto name="Order">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml);

        $generator = $this->createGenerator();
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true]);

        $this->assertFileExists($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');
        $this->assertFileExists($this->srcDir . 'Dto/Mapper/OrderDtoMapper.php');
    }

    public function testGeneratorDoesNotCreateMappersWhenDisabled(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml);

        $generator = $this->createGenerator();
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => false]);

        // DTO should exist
        $this->assertFileExists($this->srcDir . 'Dto/UserDto.php');

        // Mapper directory should not exist
        $this->assertDirectoryDoesNotExist($this->srcDir . 'Dto/Mapper');
    }

    public function testGeneratorUpdatesExistingMappers(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml);

        $generator = $this->createGenerator();

        // First generation
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true, 'force' => true]);
        $originalContent = file_get_contents($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');

        // Update config with new field
        $xml2 = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml2);

        // Second generation
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true]);
        $updatedContent = file_get_contents($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');

        $this->assertNotSame($originalContent, $updatedContent);
        $this->assertStringContainsString('string $name', $updatedContent);
    }

    public function testGeneratorDeletesRemovedMappers(): void
    {
        // Create initial config with two DTOs
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
    <dto name="Order">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml);

        $generator = $this->createGenerator();
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true, 'force' => true]);

        $this->assertFileExists($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');
        $this->assertFileExists($this->srcDir . 'Dto/Mapper/OrderDtoMapper.php');

        // Update config to remove Order
        $xml2 = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml2);

        // Regenerate
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true]);

        $this->assertFileExists($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');
        $this->assertFileDoesNotExist($this->srcDir . 'Dto/Mapper/OrderDtoMapper.php');
    }

    public function testGeneratorDryRunDoesNotCreateMapperFiles(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml);

        $generator = $this->createGenerator();
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true, 'dryRun' => true]);

        // Directories may be created in dry-run, but files should not be written
        $this->assertFileDoesNotExist($this->srcDir . 'Dto/Mapper/UserDtoMapper.php');
    }

    public function testGeneratedMapperHasValidPhpSyntax(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<dtos xmlns="php-collective-dto">
    <dto name="Complex">
        <field name="id" type="int" required="true"/>
        <field name="name" type="string" required="true"/>
        <field name="email" type="string"/>
        <field name="count" type="int"/>
        <field name="active" type="bool"/>
        <field name="tags" type="array"/>
    </dto>
</dtos>
XML;
        $this->writeConfig($xml);

        $generator = $this->createGenerator();
        $generator->generate($this->configDir, $this->srcDir, ['mapper' => true]);

        $mapperFile = $this->srcDir . 'Dto/Mapper/ComplexDtoMapper.php';
        $this->assertFileExists($mapperFile);

        exec('php -l ' . escapeshellarg($mapperFile) . ' 2>&1', $output, $returnCode);
        $this->assertSame(0, $returnCode, 'PHP syntax error: ' . implode("\n", $output));
    }

    // ===========================================
    // Helper Methods
    // ===========================================

    private function createGenerator(): Generator
    {
        $engine = new XmlEngine();
        $config = new ArrayConfig([
            'namespace' => 'Test',
        ]);
        $builder = new Builder($engine, $config);
        $renderer = new TwigRenderer(null, $config);

        return new Generator($builder, $renderer, $this->createIo(), $config);
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
