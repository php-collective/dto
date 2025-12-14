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
     *
     * @var int
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

    public function testConfirmFlagValidSyntax(): void
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
        file_put_contents($this->tempDir . '/config/dto.xml', $xml);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --confirm',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // --confirm validates syntax, should pass with valid config
        $this->assertSame(0, $result['exitCode'], 'CLI should exit with 0 when syntax is valid. Output: ' . $result['output']);
        $this->assertFileExists($this->tempDir . '/src/Dto/UserDto.php');
    }

    public function testExitCodeZeroWhenNoChanges(): void
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

        // First run to create the file
        $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Second run - no changes expected
        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Normal mode (not verbose), returns 0 even when no changes
        $this->assertSame(0, $result['exitCode'], 'CLI should exit with 0 in normal mode. Output: ' . $result['output']);
    }

    public function testExitCodeZeroInNormalModeWithChanges(): void
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
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Normal mode (not verbose/dry-run), returns 0 even when files are created
        $this->assertSame(0, $result['exitCode'], 'CLI should exit with 0 in normal mode. Output: ' . $result['output']);
        $this->assertFileExists($this->tempDir . '/src/Dto/UserDto.php');
    }

    public function testExitCodeTwoInDryRunModeWithChanges(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="Product">
        <field name="sku" type="string"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto.xml', $xml);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --dry-run',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Dry-run mode returns CODE_CHANGES (2) when changes would be made
        $this->assertSame(self::CODE_CHANGES, $result['exitCode'], 'CLI should exit with 2 in dry-run mode. Output: ' . $result['output']);
        // File should NOT be created
        $this->assertFileDoesNotExist($this->tempDir . '/src/Dto/ProductDto.php');
    }

    public function testExitCodeZeroInDryRunModeNoChanges(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="Order">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto.xml', $xml);

        // First run to create the file
        $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Second run with dry-run - no changes expected
        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --dry-run',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Dry-run mode returns 0 when no changes would be made
        $this->assertSame(0, $result['exitCode'], 'CLI should exit with 0 when no changes. Output: ' . $result['output']);
    }

    public function testForceRegeneratesAllDtos(): void
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

        // First run
        $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Second run with --force in verbose mode to verify regeneration
        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --force --verbose',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // With --force, files are regenerated even when unchanged
        $this->assertSame(self::CODE_CHANGES, $result['exitCode'], 'CLI should exit with 2 when force regenerating. Output: ' . $result['output']);
        $this->assertStringContainsString('Creating: User DTO', $result['output']);
    }

    public function testGenerateWithNestedDtoSubfolder(): void
    {
        // Create config/dto/ subfolder with multiple XML files
        mkdir($this->tempDir . '/config/dto', 0777, true);

        $userXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="name" type="string"/>
    </dto>
</dtos>
XML;
        $orderXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="Order">
        <field name="orderId" type="int"/>
        <field name="total" type="float"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto/user.xml', $userXml);
        file_put_contents($this->tempDir . '/config/dto/order.xml', $orderXml);

        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        $this->assertSame(0, $result['exitCode'], 'CLI should exit with 0. Output: ' . $result['output']);
        $this->assertFileExists($this->tempDir . '/src/Dto/UserDto.php');
        $this->assertFileExists($this->tempDir . '/src/Dto/OrderDto.php');

        // Verify both DTOs have correct content
        $userContent = file_get_contents($this->tempDir . '/src/Dto/UserDto.php');
        $this->assertStringContainsString('class UserDto', $userContent);

        $orderContent = file_get_contents($this->tempDir . '/src/Dto/OrderDto.php');
        $this->assertStringContainsString('class OrderDto', $orderContent);
    }

    public function testVerboseShowsDiffOnModification(): void
    {
        // Create initial config
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto.xml', $xml);

        // First run to create the file
        $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        // Modify config - add a new field
        $modifiedXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<dtos xmlns="php-collective-dto">
    <dto name="User">
        <field name="id" type="int"/>
        <field name="email" type="string"/>
    </dto>
</dtos>
XML;
        file_put_contents($this->tempDir . '/config/dto.xml', $modifiedXml);

        // Second run with --verbose should show diff
        $result = $this->runCli(sprintf(
            'generate --config-path=%s/config --src-path=%s/src --namespace=TestApp --verbose',
            escapeshellarg($this->tempDir),
            escapeshellarg($this->tempDir),
        ));

        $this->assertSame(self::CODE_CHANGES, $result['exitCode']);
        $this->assertStringContainsString('Changes in User DTO:', $result['output']);
        // Diff output should show added lines with + prefix
        $this->assertStringContainsString('|', $result['output']);
        // The new email field should appear in the diff
        $this->assertStringContainsString('email', $result['output']);
    }
}
