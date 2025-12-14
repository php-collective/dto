<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Engine;

interface EngineInterface {

	/**
	 * The file extension to look for.
	 *
	 * @return string
	 */
	public function extension(): string;

	/**
	 * This can hold basic file validation for each file.
	 *
	 * @param array<string> $files
	 * @return void
	 */
	public function validate(array $files): void;

	/**
	 * Transforms string into an array form.
	 *
	 * All bool, int, null values etc must be also transformed into PHP counterparts.
	 *
	 * @param string $content
	 * @return array
	 */
	public function parse(string $content): array;

}
