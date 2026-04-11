<?php

declare(strict_types=1);

namespace PhpCollective\Dto;

use InvalidArgumentException;
use PhpCollective\Dto\Dto\Dto;

/**
 * Fluent builder produced by `Mapper::map()`. Applies optional modifiers
 * (`ignoreMissing`, `withKeyType`, `only`) before dispatching to the target
 * DTO's `createFromArray()`.
 *
 * Instances are immutable in spirit — each modifier returns `$this` after
 * mutating a single setting, which keeps the fluent chain compact without
 * allocating a new object per call.
 *
 * @see \PhpCollective\Dto\Mapper
 */
final class ObjectFactory
{
    private bool $ignoreMissing = true;

    private ?string $keyType = null;

    /**
     * @var array<string>|null
     */
    private ?array $only = null;

    /**
     * @param mixed $source
     */
    public function __construct(private mixed $source)
    {
    }

    /**
     * When `true` (the default), extra keys in the source that do not match
     * any DTO field are silently ignored. When `false`, unknown keys cause
     * the target DTO to raise an exception via its standard validation.
     *
     * @param bool $ignore
     *
     * @return $this
     */
    public function ignoreMissing(bool $ignore = true)
    {
        $this->ignoreMissing = $ignore;

        return $this;
    }

    /**
     * Tell the target DTO which inflection the source keys use, so fields
     * named `my_field` / `my-field` / `myField` are matched correctly.
     *
     * @param string|null $type One of `Dto::TYPE_DEFAULT`, `Dto::TYPE_CAMEL`,
     *                          `Dto::TYPE_UNDERSCORED`, `Dto::TYPE_DASHED`.
     *
     * @return $this
     */
    public function withKeyType(?string $type)
    {
        $this->keyType = $type;

        return $this;
    }

    /**
     * Restrict the hydration to only the given field names. Useful for
     * partial updates where the source contains more data than you want
     * to apply to the target.
     *
     * NOTE: `$fields` is matched against the **source keys as they appear
     * in the input**, not against the canonical DTO field names. When
     * combined with `withKeyType()` you must pass the inflected source
     * form — e.g. with `withKeyType(Dto::TYPE_UNDERSCORED)` pass
     * `['first_name']`, not `['firstName']`. Filtering happens before
     * key-type normalization so the payload contract stays predictable.
     *
     * @param array<string> $fields
     *
     * @return $this
     */
    public function only(array $fields)
    {
        $this->only = $fields;

        return $this;
    }

    /**
     * Hydrate the target DTO class from the configured source.
     *
     * For static return-type inference, prefer the `Dto::from()` shortcut on
     * the target DTO class — it returns `static` and does not need template
     * annotations at the call site.
     *
     * @param string $target Fully-qualified DTO subclass name.
     *
     * @throws \InvalidArgumentException If the target is not a DTO class.
     *
     * @return \PhpCollective\Dto\Dto\Dto
     */
    public function to(string $target): Dto
    {
        if (!is_a($target, Dto::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Target "%s" must be a subclass of %s.',
                $target,
                Dto::class,
            ));
        }

        $data = Mapper::toArray($this->source);

        if ($this->only !== null) {
            $data = array_intersect_key($data, array_flip($this->only));
        }

        return $target::createFromArray($data, $this->ignoreMissing, $this->keyType);
    }
}
