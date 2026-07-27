<?php

declare(strict_types=1);

/**
 * php-collective/dto vs std-out/simple-data-objects.
 *
 * Identical DTO shapes on both sides. Both libraries get their compiled/generated code warmed
 * before timing, so neither pays first-call costs.
 *
 * Usage: php benchmark/run-sdo.php [iterations]
 */

use Benchmark\Dto\MiniDto;
use Benchmark\Dto\OrderDto;
use Benchmark\Dto\TransformUserDto;
use Benchmark\Dto\UserDto;
use Benchmark\SimpleDataObjects\MiniData;
use Benchmark\SimpleDataObjects\OrderData;
use Benchmark\SimpleDataObjects\TransformUserCastData;
use Benchmark\SimpleDataObjects\TransformUserData;
use Benchmark\SimpleDataObjects\UserData;
use StdOut\SimpleDataObjects\Support\MetadataRegistry;

require __DIR__ . '/vendor/autoload.php';

if (!class_exists(MetadataRegistry::class)) {
    fwrite(STDERR, "std-out/simple-data-objects is not installed (it requires PHP 8.4+).\n");
    fwrite(STDERR, "Install it first:\n\n  cd benchmark && composer require --dev std-out/simple-data-objects\n");
    exit(1);
}

if (!class_exists(UserDto::class)) {
    fwrite(STDERR, "Benchmark DTOs are not generated yet.\n");
    fwrite(STDERR, "Generate them first:\n\n  php benchmark/generate.php\n");
    exit(1);
}

$iterations = (int)($argv[1] ?? 20000);
$warmup = 2000;

$cacheDir = sys_get_temp_dir() . '/sdo-bench-cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}
MetadataRegistry::setStoragePath($cacheDir);

$flat = [
    'id' => 1,
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'phone' => '+49 123 456',
    'active' => true,
    'roles' => ['admin', 'editor'],
];

$transform = [
    'id' => 1,
    'name' => '  Jane Doe  ',
    'email' => '  JANE@EXAMPLE.COM ',
    'city' => ' berlin ',
    'code' => 'de-be',
];

/**
 * @param int $count
 *
 * @return array<string, mixed>
 */
function nested(int $count): array
{
    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $items[] = [
            'productId' => $i,
            'name' => 'Product ' . $i,
            'quantity' => 2,
            'price' => 19.99,
        ];
    }

    return [
        'id' => 42,
        'customer' => [
            'id' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+49 123 456',
            'active' => true,
            'roles' => ['admin'],
        ],
        'shippingAddress' => [
            'street' => 'Main St 1',
            'city' => 'Berlin',
            'country' => 'DE',
            'zipCode' => '10115',
        ],
        'items' => $items,
        'total' => 199.90,
        'status' => 'pending',
        // createdAt is intentionally omitted: our DTO keeps a DateTimeImmutable on toArray()
        // while their DateTimeImmutableCast formats it back to a string, so including it would
        // compare non-equivalent serialization work.
    ];
}

/**
 * @param \Closure $callback
 * @param int $iterations
 * @param int $warmup
 *
 * @return float Operations per second.
 */
function measure(Closure $callback, int $iterations, int $warmup): float
{
    for ($i = 0; $i < $warmup; $i++) {
        $callback();
    }

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    $elapsed = (hrtime(true) - $start) / 1e9;

    return $iterations / $elapsed;
}

/**
 * @param \Closure $callback
 * @param int $instances
 *
 * @return float Bytes per instance.
 */
function measureMemory(Closure $callback, int $instances = 5000): float
{
    $keep = [];
    $callback();
    gc_collect_cycles();
    $before = memory_get_usage();
    for ($i = 0; $i < $instances; $i++) {
        $keep[] = $callback();
    }
    $used = memory_get_usage() - $before;
    unset($keep);

    return $used / $instances;
}

$scenarios = [];

$mini = ['id' => 1, 'name' => 'Jane', 'active' => true];

$scenarios['hydrate mini (3 scalars)'] = [
    fn () => new MiniDto($mini),
    fn () => MiniData::from($mini),
];
$scenarios['serialize mini (3 scalars)'] = (function () use ($mini) {
    $ours = new MiniDto($mini);
    $theirs = MiniData::from($mini);

    return [
        fn () => $ours->toArray(),
        fn () => $theirs->toArray(),
    ];
})();

$scenarios['hydrate flat'] = [
    fn () => new UserDto($flat),
    fn () => UserData::from($flat),
];
$scenarios['serialize flat'] = (function () use ($flat) {
    $ours = new UserDto($flat);
    $theirs = UserData::from($flat);

    return [
        fn () => $ours->toArray(),
        fn () => $theirs->toArray(),
    ];
})();

// Their compiled Cast attribute is the fair equivalent to our compiled transformFrom.
$scenarios['hydrate transforms, their Cast'] = [
    fn () => new TransformUserDto($transform),
    fn () => TransformUserCastData::from($transform),
];
// Their Pipe middleware runs per value at runtime, so it is the slower of their two options.
$scenarios['hydrate transforms, their Pipe'] = [
    fn () => new TransformUserDto($transform),
    fn () => TransformUserData::from($transform),
];

foreach ([20, 200, 2000] as $size) {
    $payload = nested($size);
    $scenarios["hydrate nested + {$size} items"] = [
        fn () => new OrderDto($payload),
        fn () => OrderData::from($payload),
    ];

    $ours = new OrderDto($payload);
    $theirs = OrderData::from($payload);
    $scenarios["serialize nested + {$size} items"] = [
        fn () => $ours->toArray(),
        fn () => $theirs->toArray(),
    ];
}

/**
 * Normalize a callback result to a comparable array so both libraries can be checked for
 * equivalent output before timing.
 *
 * @param mixed $value
 *
 * @return mixed
 */
function normalizeResult(mixed $value): mixed
{
    if (is_object($value) && method_exists($value, 'toArray')) {
        return $value->toArray();
    }

    return $value;
}

// Fail fast if a scenario would compare non-equivalent work, e.g. a mismatched transform or a
// library changing its output shape. The benchmark ratios are only meaningful when both sides
// produce the same result.
foreach ($scenarios as $label => [$oursCallback, $theirsCallback]) {
    $ours = json_encode(normalizeResult($oursCallback()));
    $theirs = json_encode(normalizeResult($theirsCallback()));
    if ($ours !== $theirs) {
        fwrite(STDERR, "Scenario \"{$label}\" compares non-equivalent output:\n");
        fwrite(STDERR, "  php-collective/dto: {$ours}\n");
        fwrite(STDERR, "  simple-data-objects: {$theirs}\n");
        exit(1);
    }
}

echo "php-collective/dto vs std-out/simple-data-objects\n";
echo 'PHP ' . PHP_VERSION . ', JIT ' . (function_exists('opcache_get_status')
    && (opcache_get_status(false)['jit']['enabled'] ?? false) ? 'on' : 'off') . "\n";
echo "iterations: {$iterations} (warmup {$warmup})\n\n";

printf("%-34s %16s %16s %10s\n", 'scenario', 'php-collective', 'simple-data-obj', 'ratio');
echo str_repeat('-', 80), "\n";

foreach ($scenarios as $label => [$oursCallback, $theirsCallback]) {
    $scale = str_contains($label, '2000 items') ? 20 : (str_contains($label, '200 items') ? 4 : 1);
    $runs = max(200, intdiv($iterations, $scale));

    $ourOps = measure($oursCallback, $runs, min($warmup, $runs));
    $theirOps = measure($theirsCallback, $runs, min($warmup, $runs));

    printf(
        "%-34s %16s %16s %9.2fx\n",
        $label,
        number_format($ourOps),
        number_format($theirOps),
        $theirOps / $ourOps,
    );
}

echo "\nmemory per instance (flat DTO, 5000 instances)\n";
echo str_repeat('-', 80), "\n";
printf("%-34s %16s bytes\n", 'php-collective/dto', number_format(measureMemory(fn () => new UserDto($flat)), 1));
printf("%-34s %16s bytes\n", 'simple-data-objects', number_format(measureMemory(fn () => UserData::from($flat)), 1));

echo "\nratio > 1 means simple-data-objects is faster.\n";
