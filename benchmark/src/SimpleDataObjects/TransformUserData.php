<?php

declare(strict_types=1);

namespace Benchmark\SimpleDataObjects;

use Benchmark\Support\UcfirstTrimPipe;
use StdOut\SimpleDataObjects\Attributes\Pipe;
use StdOut\SimpleDataObjects\BaseData;
use StdOut\SimpleDataObjects\Pipes\LowercaseValuePipe;
use StdOut\SimpleDataObjects\Pipes\TrimValuePipe;
use StdOut\SimpleDataObjects\Pipes\UppercaseValuePipe;

class TransformUserData extends BaseData
{
    public function __construct(
        public readonly int $id,
        #[Pipe(TrimValuePipe::class)]
        public readonly string $name,
        #[Pipe(TrimValuePipe::class, LowercaseValuePipe::class)]
        public readonly string $email,
        #[Pipe(UcfirstTrimPipe::class)]
        public readonly ?string $city = null,
        #[Pipe(TrimValuePipe::class, UppercaseValuePipe::class)]
        public readonly ?string $code = null,
    ) {
    }
}
