<?php

declare(strict_types=1);

namespace PhpCollective\Dto\Config;

/**
 * Short alias for FieldBuilder.
 *
 * @method static \PhpCollective\Dto\Config\FieldBuilder string(string $name)
 * @method static \PhpCollective\Dto\Config\FieldBuilder int(string $name)
 * @method static \PhpCollective\Dto\Config\FieldBuilder float(string $name)
 * @method static \PhpCollective\Dto\Config\FieldBuilder bool(string $name)
 * @method static \PhpCollective\Dto\Config\FieldBuilder array(string $name, ?string $elementType = null)
 * @method static \PhpCollective\Dto\Config\FieldBuilder dto(string $name, string $dtoName)
 * @method static \PhpCollective\Dto\Config\FieldBuilder collection(string $name, string $elementType)
 * @method static \PhpCollective\Dto\Config\FieldBuilder class(string $name, string $className)
 * @method static \PhpCollective\Dto\Config\FieldBuilder enum(string $name, string $enumClass)
 * @method static \PhpCollective\Dto\Config\FieldBuilder mixed(string $name)
 * @method static \PhpCollective\Dto\Config\FieldBuilder union(string $name, string ...$types)
 * @method static \PhpCollective\Dto\Config\FieldBuilder of(string $name, string $type)
 */
class Field extends FieldBuilder
{
}
