<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Test\Generator;

use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\IoInterface;
use PHPUnit\Framework\TestCase;

class ConsoleIoTest extends TestCase
{
    /**
     * @var resource
     */
    protected $stdout;

    /**
     * @var resource
     */
    protected $stderr;

    protected function setUp(): void
    {
        $this->stdout = fopen('php://memory', 'r+');
        $this->stderr = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
    }

    protected function getStdoutOutput(): string
    {
        rewind($this->stdout);

        return stream_get_contents($this->stdout);
    }

    protected function getStderrOutput(): string
    {
        rewind($this->stderr);

        return stream_get_contents($this->stderr);
    }

    public function testOutNormalLevel(): void
    {
        $io = new ConsoleIo(IoInterface::NORMAL, $this->stdout, $this->stderr);
        $io->out('Test message');

        $this->assertSame("Test message\n", $this->getStdoutOutput());
    }

    public function testOutQuietLevel(): void
    {
        $io = new ConsoleIo(IoInterface::QUIET, $this->stdout, $this->stderr);
        $io->out('Test message', 1, IoInterface::NORMAL);

        $this->assertSame('', $this->getStdoutOutput());
    }

    public function testOutVerboseLevel(): void
    {
        $io = new ConsoleIo(IoInterface::VERBOSE, $this->stdout, $this->stderr);
        $io->out('Test message', 1, IoInterface::VERBOSE);

        $this->assertSame("Test message\n", $this->getStdoutOutput());
    }

    public function testVerboseOnlyShowsWhenVerbose(): void
    {
        $io = new ConsoleIo(IoInterface::NORMAL, $this->stdout, $this->stderr);
        $io->verbose('Verbose message');

        $this->assertSame('', $this->getStdoutOutput());

        // Reset streams
        fclose($this->stdout);
        $this->stdout = fopen('php://memory', 'r+');

        $io = new ConsoleIo(IoInterface::VERBOSE, $this->stdout, $this->stderr);
        $io->verbose('Verbose message');

        $this->assertSame("Verbose message\n", $this->getStdoutOutput());
    }

    public function testQuietAlwaysShows(): void
    {
        $io = new ConsoleIo(IoInterface::QUIET, $this->stdout, $this->stderr);
        $io->quiet('Quiet message');

        $this->assertSame("Quiet message\n", $this->getStdoutOutput());
    }

    public function testMultipleNewlines(): void
    {
        $io = new ConsoleIo(IoInterface::NORMAL, $this->stdout, $this->stderr);
        $io->out('Test', 3);

        $this->assertSame("Test\n\n\n", $this->getStdoutOutput());
    }

    public function testSuccessNormalLevel(): void
    {
        $io = new ConsoleIo(IoInterface::NORMAL, $this->stdout, $this->stderr);
        $io->success('Success message');

        // Output may or may not have color codes depending on terminal
        $this->assertStringContainsString('Success message', $this->getStdoutOutput());
    }

    public function testSuccessQuietLevel(): void
    {
        $io = new ConsoleIo(IoInterface::QUIET, $this->stdout, $this->stderr);
        $io->success('Success message', 1, IoInterface::NORMAL);

        $this->assertSame('', $this->getStdoutOutput());
    }

    public function testOutWithArray(): void
    {
        $io = new ConsoleIo(IoInterface::NORMAL, $this->stdout, $this->stderr);
        $io->verbose(['Line 1', 'Line 2']);

        $this->assertSame('', $this->getStdoutOutput());

        // Reset streams
        fclose($this->stdout);
        $this->stdout = fopen('php://memory', 'r+');

        $io = new ConsoleIo(IoInterface::VERBOSE, $this->stdout, $this->stderr);
        $io->verbose(['Line 1', 'Line 2']);

        $this->assertSame("Line 1\nLine 2\n", $this->getStdoutOutput());
    }

    public function testErrorGoesToStderr(): void
    {
        $io = new ConsoleIo(IoInterface::NORMAL, $this->stdout, $this->stderr);
        $io->error('Error message');

        $this->assertSame('', $this->getStdoutOutput());
        $this->assertStringContainsString('Error message', $this->getStderrOutput());
    }
}
