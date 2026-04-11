<?php

declare(strict_types=1);

namespace PhpCollective\Dto;

use InvalidArgumentException;
use JsonSerializable;
use PhpCollective\Dto\Dto\Dto;
use PhpCollective\Dto\Dto\FromArrayToArrayInterface;
use PhpCollective\Dto\Utility\Json;
use Throwable;

/**
 * Unified entry point for hydrating DTOs from heterogeneous sources.
 *
 * Instead of picking the right constructor for each input shape
 * (`createFromArray`, `fromUnserialized`, `new + fromArray`, ...), callers use
 * a single `Mapper::map($source)->to(Target::class)` call and the facade
 * figures out how to extract an array payload from the source.
 *
 * Supported sources out of the box:
 * - `array`
 * - `PhpCollective\Dto\Dto\Dto` instance (uses `touchedToArray()`)
 * - `PhpCollective\Dto\Dto\FromArrayToArrayInterface` implementor
 * - `JsonSerializable` implementor
 * - JSON `string`
 * - any plain `object` (via `get_object_vars()`)
 *
 * Example:
 * ```php
 * use PhpCollective\Dto\Mapper;
 *
 * $dto = Mapper::map($request->getParsedBody())->to(UserDto::class);
 * $copy = Mapper::map($existingDto)->to(UserDto::class);
 * $strict = Mapper::map($jsonString)
 *     ->ignoreMissing(false)
 *     ->withKeyType(Dto::TYPE_UNDERSCORED)
 *     ->to(UserDto::class);
 * ```
 *
 * For the common DTO-in, DTO-out case prefer the typed shortcut on the target
 * DTO: `UserDto::from($source)`. It returns `static`, so PHPStan infers the
 * concrete DTO type without template annotations.
 */
final class Mapper
{
    /**
     * Begin a fluent mapping from `$source`.
     *
     * @param mixed $source
     *
     * @return \PhpCollective\Dto\ObjectFactory
     */
    public static function map(mixed $source): ObjectFactory
    {
        return new ObjectFactory($source);
    }

    /**
     * Extract an array payload from an arbitrary supported source.
     *
     * Exposed as a static helper so `Dto::from()` and `ObjectFactory::to()`
     * share the same detection logic.
     *
     * @param mixed $source
     *
     * @throws \InvalidArgumentException If the source shape is not supported.
     *
     * @return array<string, mixed>
     */
    public static function toArray(mixed $source): array
    {
        if (is_array($source)) {
            self::assertAssociative($source, 'array');

            return $source;
        }

        if ($source instanceof Dto) {
            return $source->touchedToArray();
        }

        if ($source instanceof FromArrayToArrayInterface) {
            return $source->toArray();
        }

        if ($source instanceof JsonSerializable) {
            $data = $source->jsonSerialize();
            if (is_array($data)) {
                self::assertAssociative($data, 'JsonSerializable payload');

                return $data;
            }
            if (is_object($data)) {
                return get_object_vars($data);
            }

            throw new InvalidArgumentException(sprintf(
                'JsonSerializable source returned unsupported payload of type "%s".',
                get_debug_type($data),
            ));
        }

        if (is_string($source)) {
            try {
                $decoded = (new Json())->decode($source, true);
            } catch (Throwable $e) {
                throw new InvalidArgumentException(
                    'String source could not be decoded as JSON: ' . $e->getMessage(),
                    0,
                    $e,
                );
            }
            if (!is_array($decoded)) {
                throw new InvalidArgumentException(
                    'String source could not be decoded as a JSON object into an array.',
                );
            }
            self::assertAssociative($decoded, 'JSON string');

            return $decoded;
        }

        if (is_object($source)) {
            return get_object_vars($source);
        }

        throw new InvalidArgumentException(sprintf(
            'Cannot map source of type "%s" — expected array, object, or JSON string.',
            get_debug_type($source),
        ));
    }

    /**
     * Reject list arrays (e.g. `[1, 2, 3]` or JSON `"[1, 2]"`). DTO hydration
     * operates on associative `array<string, mixed>` payloads and would later
     * produce a confusing `TypeError` from `Dto::hasField(string)` if given an
     * integer-keyed sequence. Failing here yields a clearer error at the
     * actual misuse site.
     *
     * @param array<array-key, mixed> $data
     * @param string $sourceDescription Human-readable source label for the message.
     *
     * @throws \InvalidArgumentException If `$data` is a list or has any non-string keys.
     *
     * @return void
     */
    private static function assertAssociative(array $data, string $sourceDescription): void
    {
        if ($data === []) {
            return;
        }

        if (array_is_list($data)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot map %s: expected an associative array keyed by field names, got a list.',
                $sourceDescription,
            ));
        }

        foreach ($data as $key => $_) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot map %s: all keys must be strings, got "%s".',
                    $sourceDescription,
                    get_debug_type($key),
                ));
            }
        }
    }
}
