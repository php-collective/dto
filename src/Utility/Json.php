<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Utility;

use JsonException;
use RuntimeException;

/**
 * JSON utility class.
 */
class Json
{
    /**
     * Encode data to JSON.
     *
     * @param mixed $data
     * @param int $flags
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function encode(mixed $data, int $flags = JSON_THROW_ON_ERROR): string
    {
        try {
            $result = json_encode($data, $flags);
            assert($result !== false);

            return $result;
        } catch (JsonException $e) {
            throw new RuntimeException('JSON encoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decode JSON to array.
     *
     * @param string $json
     * @param bool $assoc
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public function decode(string $json, bool $assoc = true): mixed
    {
        try {
            return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('JSON decoding failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
