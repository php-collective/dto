<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Interface for console I/O operations.
 */
interface IoInterface {

	public const QUIET = 0;
	public const NORMAL = 1;
	public const VERBOSE = 2;

	/**
	 * Output at the verbose level.
	 *
	 * @param array<string>|string $message
	 * @param int $newlines
	 * @return int|null
	 */
	public function verbose(array|string $message, int $newlines = 1): ?int;

	/**
	 * Output at all levels.
	 *
	 * @param array<string>|string $message
	 * @param int $newlines
	 * @return int|null
	 */
	public function quiet(array|string $message, int $newlines = 1): ?int;

	/**
	 * Outputs a single or multiple messages to stdout.
	 *
	 * @param string|null $message
	 * @param int $newlines
	 * @param int $level
	 * @return int|null
	 */
	public function out(?string $message = null, int $newlines = 1, int $level = self::NORMAL): ?int;

	/**
	 * Outputs an error message.
	 *
	 * @param string|null $message
	 * @param int $newlines
	 * @return int|null
	 */
	public function error(?string $message = null, int $newlines = 1): ?int;

	/**
	 * Outputs a success message.
	 *
	 * @param string|null $message
	 * @param int $newlines
	 * @param int $level
	 * @return int|null
	 */
	public function success(?string $message = null, int $newlines = 1, int $level = self::NORMAL): ?int;

	/**
	 * Aborts execution with an error message.
	 *
	 * @param string $message
	 * @param int $exitCode
	 * @return void
	 */
	public function abort(string $message, int $exitCode = 1): void;

}
