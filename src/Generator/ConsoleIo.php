<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Console I/O implementation for standalone CLI usage.
 */
class ConsoleIo implements IoInterface
{
    protected int $verbosity;

    /**
     * @var resource
     */
    protected $stdout;

    /**
     * @var resource
     */
    protected $stderr;

    /**
     * @param int $verbosity Verbosity level (QUIET, NORMAL, VERBOSE)
     * @param resource|null $stdout Output stream (defaults to STDOUT)
     * @param resource|null $stderr Error stream (defaults to STDERR)
     */
    public function __construct(int $verbosity = self::NORMAL, $stdout = null, $stderr = null)
    {
        $this->verbosity = $verbosity;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    /**
     * @inheritDoc
     */
    public function verbose(array|string $message, int $newlines = 1): ?int
    {
        if ($this->verbosity < self::VERBOSE) {
            return null;
        }

        return $this->write($message, $newlines);
    }

    /**
     * @inheritDoc
     */
    public function quiet(array|string $message, int $newlines = 1): ?int
    {
        return $this->write($message, $newlines);
    }

    /**
     * @inheritDoc
     */
    public function out(?string $message = null, int $newlines = 1, int $level = self::NORMAL): ?int
    {
        if ($this->verbosity < $level) {
            return null;
        }

        return $this->write($message ?? '', $newlines);
    }

    /**
     * @inheritDoc
     */
    public function error(?string $message = null, int $newlines = 1): ?int
    {
        $output = $message ?? '';
        if ($this->supportsColor($this->stderr)) {
            $output = "\033[31m" . $output . "\033[0m";
        }

        return $this->write($output, $newlines, $this->stderr);
    }

    /**
     * @inheritDoc
     */
    public function success(?string $message = null, int $newlines = 1, int $level = self::NORMAL): ?int
    {
        if ($this->verbosity < $level) {
            return null;
        }

        $output = $message ?? '';
        if ($this->supportsColor($this->stdout)) {
            $output = "\033[32m" . $output . "\033[0m";
        }

        return $this->write($output, $newlines);
    }

    /**
     * @inheritDoc
     */
    public function abort(string $message, int $exitCode = 1): void
    {
        $this->error($message);

        exit($exitCode);
    }

    /**
     * Write message to stream.
     *
     * @param array<string>|string $message
     * @param int $newlines
     * @param resource|null $stream
     *
     * @return int
     */
    protected function write(array|string $message, int $newlines = 1, $stream = null): int
    {
        if (is_array($message)) {
            $message = implode(PHP_EOL, $message);
        }

        $message .= str_repeat(PHP_EOL, $newlines);
        fwrite($stream ?? $this->stdout, $message);

        return strlen($message);
    }

    /**
     * Check if the stream supports ANSI colors.
     *
     * @param resource $stream
     *
     * @return bool
     */
    protected function supportsColor($stream): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return (function_exists('sapi_windows_vt100_support') && sapi_windows_vt100_support($stream))
                || getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON';
        }

        return function_exists('stream_isatty') && stream_isatty($stream);
    }
}
