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
     * @param int $verbosity Verbosity level (QUIET, NORMAL, VERBOSE)
     */
    public function __construct(int $verbosity = self::NORMAL)
    {
        $this->verbosity = $verbosity;
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
        if ($this->supportsColor(STDERR)) {
            $output = "\033[31m" . $output . "\033[0m";
        }

        return $this->write($output, $newlines, STDERR);
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
        if ($this->supportsColor(STDOUT)) {
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
     * @param resource $stream
     *
     * @return int
     */
    protected function write(array|string $message, int $newlines = 1, $stream = STDOUT): int
    {
        if (is_array($message)) {
            $message = implode(PHP_EOL, $message);
        }

        $message .= str_repeat(PHP_EOL, $newlines);
        fwrite($stream, $message);

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
