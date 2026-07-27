<?php

declare(strict_types=1);

namespace Benchmark\SimpleDataObjects;

use Benchmark\Support\UcfirstCast;
use StdOut\SimpleDataObjects\Attributes\Cast;
use StdOut\SimpleDataObjects\BaseData;
use StdOut\SimpleDataObjects\Casts\TrimCast;

class TransformUserCastData extends BaseData
{
    public function __construct(
        public readonly int $id,
        #[Cast(new TrimCast())]
        public readonly string $name,
        #[Cast(new TrimCast(TrimCast::LOWERCASE))]
        public readonly string $email,
        #[Cast(new UcfirstCast())]
        public readonly ?string $city = null,
        #[Cast(new TrimCast(TrimCast::UPPERCASE))]
        public readonly ?string $code = null,
    ) {
    }
}
