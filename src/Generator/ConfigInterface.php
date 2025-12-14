<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Interface for configuration access.
 */
interface ConfigInterface {

	/**
	 * Get a configuration value.
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed;

	/**
	 * Get all configuration values.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array;

}
