<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Engine;

use InvalidArgumentException;
use PhpCollective\Dto\Engine\PhpEngine;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PhpEngineTest extends TestCase
{
    /**
     * @var string
     */
    protected string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = sys_get_temp_dir() . '/dto-php-engine-test-' . uniqid();
        mkdir($this->fixtureDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->fixtureDir);
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

    protected function createFixture(string $filename, string $content): string
    {
        $path = $this->fixtureDir . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    public function testExtension(): void
    {
        $engine = new PhpEngine();
        $this->assertSame('php', $engine->extension());
    }

    public function testParseFile(): void
    {
        $path = $this->createFixture('dto.php', <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'id' => 'int',
            'name' => 'string',
            'email' => 'string',
        ],
    ],
];
PHP);

        $engine = new PhpEngine();
        $result = $engine->parseFile($path);

        $this->assertArrayHasKey('User', $result);
        $this->assertSame('User', $result['User']['name']);
        $this->assertArrayHasKey('fields', $result['User']);
        $this->assertArrayHasKey('id', $result['User']['fields']);
        $this->assertSame('int', $result['User']['fields']['id']['type']);
    }

    public function testParseFileWithFullFieldDefinition(): void
    {
        $path = $this->createFixture('dto.php', <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'id' => [
                'type' => 'int',
                'required' => true,
            ],
            'name' => [
                'type' => 'string',
                'defaultValue' => 'Guest',
            ],
        ],
    ],
];
PHP);

        $engine = new PhpEngine();
        $result = $engine->parseFile($path);

        $this->assertArrayHasKey('User', $result);
        $this->assertTrue($result['User']['fields']['id']['required']);
        $this->assertSame('Guest', $result['User']['fields']['name']['defaultValue']);
    }

    public function testParseFileMultipleDtos(): void
    {
        $path = $this->createFixture('dto.php', <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'id' => 'int',
        ],
    ],
    'Article' => [
        'fields' => [
            'title' => 'string',
        ],
    ],
];
PHP);

        $engine = new PhpEngine();
        $result = $engine->parseFile($path);

        $this->assertArrayHasKey('User', $result);
        $this->assertArrayHasKey('Article', $result);
    }

    public function testParseFileWithDtoOptions(): void
    {
        $path = $this->createFixture('dto.php', <<<'PHP'
<?php
return [
    'ImmutableUser' => [
        'immutable' => true,
        'fields' => [
            'id' => 'int',
        ],
    ],
];
PHP);

        $engine = new PhpEngine();
        $result = $engine->parseFile($path);

        $this->assertArrayHasKey('ImmutableUser', $result);
        $this->assertTrue($result['ImmutableUser']['immutable']);
    }

    public function testParseFileNotFoundThrowsException(): void
    {
        $engine = new PhpEngine();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');
        $engine->parseFile('/nonexistent/path/dto.php');
    }

    public function testParseFileInvalidReturnThrowsException(): void
    {
        $path = $this->createFixture('invalid.php', <<<'PHP'
<?php
return "not an array";
PHP);

        $engine = new PhpEngine();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must return an array');
        $engine->parseFile($path);
    }

    public function testParseFileInvalidDtoConfigThrowsException(): void
    {
        $path = $this->createFixture('invalid.php', <<<'PHP'
<?php
return [
    'User' => 'not an array',
];
PHP);

        $engine = new PhpEngine();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('configuration must be an array');
        $engine->parseFile($path);
    }

    public function testParseFileInvalidFieldsThrowsException(): void
    {
        $path = $this->createFixture('invalid.php', <<<'PHP'
<?php
return [
    'User' => [
        'fields' => 'not an array',
    ],
];
PHP);

        $engine = new PhpEngine();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fields must be an array');
        $engine->parseFile($path);
    }

    public function testParseThrowsException(): void
    {
        $engine = new PhpEngine();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not support parsing string content');
        $engine->parse('<?php return [];');
    }

    public function testValidate(): void
    {
        $path = $this->createFixture('dto.php', '<?php return [];');
        $engine = new PhpEngine();
        $engine->validate([$path]);
        $this->assertTrue(true);
    }

    public function testValidateUnreadableThrowsException(): void
    {
        $engine = new PhpEngine();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not readable');
        $engine->validate(['/nonexistent/file.php']);
    }
}
