<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Generator;

/**
 * Compiles schema transform callables into generated PHP expressions.
 */
class TransformCompiler
{
    /**
     * @var string
     */
    protected const CALLABLE_PATTERN = '/^\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*(::[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)?$/';

    /**
     * @var string
     */
    protected const SIMPLE_EXPR_PATTERN = '/^\$[A-Za-z_]\w*(\[[^\[\]]+\]|->[A-Za-z_]\w*)*$/';

    /**
     * Compile a transform callable into a generated PHP expression.
     *
     * @param string|null $callable Transform callable from the schema
     * @param string $expr Generated PHP expression to transform
     * @param bool $guardNull Whether null values must bypass the transform
     * @param bool $strictTypes Whether the generated file declares strict types
     *
     * @return string
     */
    public static function compile(?string $callable, string $expr, bool $guardNull = false, bool $strictTypes = false): string
    {
        if ($callable === null || $callable === '') {
            return $expr;
        }

        // An inlined call takes its argument coercion from the generated file, while the
        // runtime dispatch takes it from Dto.php, which is strict. Inline only when the
        // generated file is strict as well, so scalar type hints keep raising TypeError.
        if (!$strictTypes) {
            return self::fallback($callable, $expr);
        }

        if (!preg_match(self::CALLABLE_PATTERN, $callable)) {
            return self::fallback($callable, $expr);
        }

        // Only inline what resolves while generating. Anything unresolved keeps the runtime
        // dispatch so a typo in the schema still surfaces as InvalidArgumentException instead
        // of a raw Error from the generated code.
        if (!is_callable($callable)) {
            return self::fallback($callable, $expr);
        }

        if ($guardNull && !preg_match(self::SIMPLE_EXPR_PATTERN, $expr)) {
            return self::fallback($callable, $expr);
        }

        $call = '\\' . ltrim($callable, '\\') . '(' . $expr . ')';

        if ($guardNull) {
            // Parenthesized so the expression stays safe in any surrounding context.
            return '(' . $expr . ' === null ? null : ' . $call . ')';
        }

        return $call;
    }

    /**
     * Compile the existing runtime transform dispatch.
     *
     * @param string $callable Transform callable from the schema
     * @param string $expr Generated PHP expression to transform
     *
     * @return string
     */
    protected static function fallback(string $callable, string $expr): string
    {
        return '$this->transformValue(\'' . addcslashes($callable, "'\\") . '\', ' . $expr . ')';
    }
}
