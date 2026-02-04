<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Engine;

use DOMDocument;
use InvalidArgumentException;
use LibXMLError;
use PhpCollective\Dto\Engine\XmlValidator;
use PHPUnit\Framework\TestCase;

class XmlValidatorTest extends TestCase
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/xml-validator-test-' . uniqid();
        mkdir($this->tempDir);
        // Reset XSD path to default
        XmlValidator::setXsdPath(dirname(__DIR__, 2) . '/config/dto.xsd');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->tempDir);
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = scandir($dir) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    protected function createXmlFile(string $filename, string $content): string
    {
        $path = $this->tempDir . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    public function testValidateValidXml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;
        $path = $this->createXmlFile('valid.xml', $xml);

        // Should not throw
        XmlValidator::validate($path);
        $this->assertTrue(true);
    }

    public function testValidateInvalidXmlMissingRequiredAttribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id"/>
    </dto>
</dtos>
XML;
        $path = $this->createXmlFile('invalid.xml', $xml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('type');
        XmlValidator::validate($path);
    }

    public function testValidateInvalidXmlMissingDtoName(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto>
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        $path = $this->createXmlFile('invalid.xml', $xml);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name');
        XmlValidator::validate($path);
    }

    public function testValidateInvalidXmlUnknownElement(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <unknown name="id" type="int"/>
    </dto>
</dtos>
XML;
        $path = $this->createXmlFile('invalid.xml', $xml);

        $this->expectException(InvalidArgumentException::class);
        XmlValidator::validate($path);
    }

    public function testGetXsdPathDefault(): void
    {
        // Reset to trigger default path
        XmlValidator::setXsdPath(dirname(__DIR__, 2) . '/config/dto.xsd');
        $path = XmlValidator::getXsdPath();

        $this->assertStringEndsWith('dto.xsd', $path);
        $this->assertFileExists($path);
    }

    public function testSetXsdPath(): void
    {
        $customPath = '/custom/path/schema.xsd';
        XmlValidator::setXsdPath($customPath);

        $this->assertSame($customPath, XmlValidator::getXsdPath());
    }

    public function testFormatErrorReturnsNullForWarning(): void
    {
        $error = new LibXMLError();
        $error->level = LIBXML_ERR_WARNING;
        $error->code = 123;
        $error->message = 'Warning message';
        $error->file = '';
        $error->line = 1;

        $result = XmlValidator::formatError($error);

        $this->assertNull($result);
    }

    public function testFormatErrorFormatsError(): void
    {
        $error = new LibXMLError();
        $error->level = LIBXML_ERR_ERROR;
        $error->code = 456;
        $error->message = 'Error message';
        $error->file = '/path/to/file.xml';
        $error->line = 10;

        $result = XmlValidator::formatError($error);

        $this->assertStringContainsString('Error `456`', $result);
        $this->assertStringContainsString('Error message', $result);
        $this->assertStringContainsString('/path/to/file.xml', $result);
        $this->assertStringContainsString('line `10`', $result);
    }

    public function testFormatErrorFormatsFatalError(): void
    {
        $error = new LibXMLError();
        $error->level = LIBXML_ERR_FATAL;
        $error->code = 789;
        $error->message = 'Fatal error message';
        $error->file = '';
        $error->line = 5;

        $result = XmlValidator::formatError($error);

        $this->assertStringContainsString('Fatal Error `789`', $result);
        $this->assertStringContainsString('Fatal error message', $result);
        $this->assertStringContainsString('line `5`', $result);
    }

    public function testFormatErrorWithoutFile(): void
    {
        $error = new LibXMLError();
        $error->level = LIBXML_ERR_ERROR;
        $error->code = 100;
        $error->message = 'Some error';
        $error->file = '';
        $error->line = 1;

        $result = XmlValidator::formatError($error);

        $this->assertStringNotContainsString(' in `', $result);
    }

    public function testGetErrorsReturnsFormattedErrors(): void
    {
        // Trigger some XML errors
        libxml_use_internal_errors(true);
        $xml = new DOMDocument();
        $xml->loadXML('<invalid><xml>');

        $errors = XmlValidator::getErrors();

        $this->assertIsArray($errors);
        // Errors should be cleared after getting them
        $this->assertEmpty(libxml_get_errors());
    }

    public function testValidateEmptyDtos(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
</dtos>
XML;
        $path = $this->createXmlFile('empty.xml', $xml);

        // Should not throw - empty DTOs is valid
        XmlValidator::validate($path);
        $this->assertTrue(true);
    }

    public function testValidateWithAllFieldAttributes(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="ComplexDto" immutable="true" extends="BaseDto">
        <field name="items" type="Item[]" collection="true" singular="item" associative="true" key="id"/>
        <field name="count" type="int" required="true" defaultValue="0"/>
        <field name="oldField" type="string" deprecated="Use newField instead"/>
        <field name="custom" type="MyClass" factory="MyFactory::create" transformFrom="MyTransformer::from" transformTo="MyTransformer::to"/>
    </dto>
</dtos>
XML;
        $path = $this->createXmlFile('complex.xml', $xml);

        // Should not throw
        XmlValidator::validate($path);
        $this->assertTrue(true);
    }
}
