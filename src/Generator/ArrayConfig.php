<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Simple array-based configuration implementation.
 */
class ArrayConfig implements ConfigInterface
{
 /**
  * @var array<string, mixed>
  */
    protected array $config;

    /**
     * Default configuration values.
     *
     * @var array<string, mixed>
     */
    protected static array $defaults = [
        'scalarAndReturnTypes' => true,
        'typedConstants' => false,
        'defaultCollectionType' => '\ArrayObject',
        'debug' => false,
        'immutable' => false,
        'suffix' => 'Dto',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(static::$defaults, $config);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Set the global defaults.
     *
     * @param array<string, mixed> $defaults
     *
     * @return void
     */
    public static function setDefaults(array $defaults): void
    {
        static::$defaults = array_merge(static::$defaults, $defaults);
    }
}
