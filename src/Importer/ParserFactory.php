<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Importer;

use InvalidArgumentException;
use PhpCollective\Dto\Importer\Parser\Config;
use PhpCollective\Dto\Importer\Parser\ParserInterface;

/**
 * Factory for creating schema parsers.
 */
class ParserFactory
{
    /**
     * Create a parser instance by type.
     *
     * @param string $type Parser type name
     *
     * @throws \InvalidArgumentException If type is unknown
     *
     * @return \PhpCollective\Dto\Importer\Parser\ParserInterface
     */
    public static function create(string $type): ParserInterface
    {
        $types = Config::types();
        $class = $types[$type] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException(sprintf(
                'Unknown parser type "%s". Available types: %s',
                $type,
                implode(', ', array_keys($types)),
            ));
        }

        return new $class();
    }
}
