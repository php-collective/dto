<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Cli;

use PHPUnit\Framework\TestCase;

class CliTest extends TestCase
{
    protected string $binPath;
    protected string $tempDir;

    protected function setUp(): void
    {
        $this->binPath = dirname(__DIR__, 2) . '/bin/dto';
        $this->tempDir = sys_get_temp_dir() . '/dto_cli_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        mkdir($this->tempDir . '/config', 0777, true);
        mkdir($this->tempDir . '/src', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
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

    /**
     * Exit code when changes were made (in verbose/dry-run mode).
     */
    protected const CODE_CHANGES = 2;

    protected function runCli(string $args = ''): array
    {
        $cmd = sprintf('php %s %s 2>&1', escapeshellarg($this->binPath), $args);
        exec($cmd, $output, $exitCode);

        return [
            'output' => implode("\n", $output),
            'exitCode' => $exitCode,
        ];
    }

    public function testHelp(): void
    {
        $result = $this->runCli('--help');

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('DTO Generator', $result['output']);
        $this->assertStringContainsString('--config-path', $result['output']);
        $this->assertStringContainsString('--src-path', $result['output']);
        $this->assertStringContainsString('--namespace', $result['output']);
        $this->assertStringContainsString('--dry-run', $result['output']);
    }

    public function testHelpShort(): void
    {
        $result = $this->runCli('-h');

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('DTO Generator', $result['output']);
    }

    public function testInvalidConfigPath(): void
    {
        $result = $this->runCli('generate --config-path=/nonexistent/path');

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString('does not exist', $result['output']);
    }

    public function testUnknownCommand(): void
    {
        $result = $this->runCli('unknown');

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString('Unknown command', $result['output']);
    }

    public function testGenerateWithXmlConfig(): void
    {
        // Create a simple XML config
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto.xml', $xml);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        $this->assertSame(0, $result['exitCode'], 'CLI should exit with 0. Output: ' . $result['output']);
        $this->assertFileExists($this->tempDir . '/src/Dto/UserDto.php');

        $content = file_get_contents($this->tempDir . '/src/Dto/UserDto.php');
        $this->assertStringContainsString('namespace TestApp\Dto;', $content);
        $this->assertStringContainsString('class UserDto', $content);
    }

    public function testGenerateWithNeonConfig(): void
    {
        // Create a simple NEON config
        $neon = <<<'NEON'
User:
    fields:
        id: int
        name: string
NEON;
        file_put_contents($this->tempDir . '/config/dto.neon', $neon);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --format=neon',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        $this->assertSame(0, $result['exitCode']);
        $this->assertFileExists($this->tempDir . '/src/Dto/UserDto.php');
    }

    public function testGenerateDryRun(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto.xml', $xml);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --dry-run',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // In dry-run mode, returns CODE_CHANGES (2) if changes would be made
        $this->assertSame(self::CODE_CHANGES, $result['exitCode'], 'CLI should exit with 2 (changes). Output: ' . $result['output']);
        // File should NOT be created in dry-run mode
        $this->assertFileDoesNotExist($this->tempDir . '/src/Dto/UserDto.php');
    }

    public function testGenerateVerbose(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto.xml', $xml);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --verbose',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // In verbose mode, returns CODE_CHANGES (2) if changes were made
        $this->assertSame(self::CODE_CHANGES, $result['exitCode'], 'CLI should exit with 2 (changes). Output: ' . $result['output']);
        $this->assertStringContainsString('Namespace:', $result['output']);
        $this->assertStringContainsString('Format:', $result['output']);
    }

    public function testGenerateWithPhpConfig(): void
    {
        // Create a simple PHP config
        $php = <<<'PHP'
<?php
return [
    'User' => [
        'fields' => [
            'id' => 'int',
            'name' => 'string',
        ],
    ],
];
PHP;
        file_put_contents($this->tempDir . '/config/dto.php', $php);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --format=php',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        $this->assertSame(0, $result['exitCode']);
        $this->assertFileExists($this->tempDir . '/src/Dto/UserDto.php');
    }

    public function testFormatAutoDetection(): void
    {
        // Create NEON config and let it auto-detect
        $neon = <<<'NEON'
User:
    fields:
        id: int
NEON;
        file_put_contents($this->tempDir . '/config/dto.neon', $neon);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --verbose',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // In verbose mode, returns CODE_CHANGES (2) if changes were made
        $this->assertSame(self::CODE_CHANGES, $result['exitCode']);
        $this->assertStringContainsString('Format: neon', $result['output']);
    }
}
