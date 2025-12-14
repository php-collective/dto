<?php

declare(strict_types=1);

/**
 * Benchmark bootstrap - loads autoloader and defines helper functions.
 */

// Load main project autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load benchmark-specific dependencies if available (for external library comparisons)
$benchmarkVendor = __DIR__ . '/vendor/autoload.php';
if (file_exists($benchmarkVendor)) {
    require_once $benchmarkVendor;
}

// Bootstrap minimal Laravel container for spatie/laravel-data
if (class_exists(\Illuminate\Container\Container::class) && class_exists(\Spatie\LaravelData\Data::class)) {
    $app = new \Illuminate\Container\Container();
    \Illuminate\Container\Container::setInstance($app);

    // Register config repository with full laravel-data config
    $app->singleton('config', function () {
        return new \Illuminate\Config\Repository([
            'data' => [
                'date_format' => DATE_ATOM,
                'date_timezone' => null,
                'features' => [
                    'cast_and_transform_iterables' => false,
                    'ignore_exception_when_trying_to_set_computed_property_value' => false,
                ],
                'transformers' => [
                    DateTimeInterface::class => \Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer::class,
                    \Illuminate\Contracts\Support\Arrayable::class => \Spatie\LaravelData\Transformers\ArrayableTransformer::class,
                    BackedEnum::class => \Spatie\LaravelData\Transformers\EnumTransformer::class,
                ],
                'casts' => [
                    DateTimeInterface::class => \Spatie\LaravelData\Casts\DateTimeInterfaceCast::class,
                    BackedEnum::class => \Spatie\LaravelData\Casts\EnumCast::class,
                ],
                'rule_inferrers' => [
                    \Spatie\LaravelData\RuleInferrers\SometimesRuleInferrer::class,
                    \Spatie\LaravelData\RuleInferrers\NullableRuleInferrer::class,
                    \Spatie\LaravelData\RuleInferrers\RequiredRuleInferrer::class,
                    \Spatie\LaravelData\RuleInferrers\BuiltInTypesRuleInferrer::class,
                    \Spatie\LaravelData\RuleInferrers\AttributesRuleInferrer::class,
                ],
                'normalizers' => [
                    \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
                    \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
                    \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
                    \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
                ],
                'wrap' => null,
                'var_dumper_caster_mode' => 'development',
                'structure_caching' => [
                    'enabled' => false,
                    'directories' => [],
                    'cache' => [
                        'store' => 'array',
                        'prefix' => 'laravel-data',
                        'duration' => null,
                    ],
                    'reflection_discovery' => [
                        'enabled' => false,
                        'base_path' => __DIR__,
                        'root_namespace' => null,
                    ],
                ],
                'validation_strategy' => \Spatie\LaravelData\Support\Creation\ValidationStrategy::OnlyRequests->value,
                'name_mapping_strategy' => [
                    'input' => null,
                    'output' => null,
                ],
                'ignore_invalid_partials' => false,
                'max_transformation_depth' => null,
                'throw_when_max_transformation_depth_reached' => true,
                'livewire' => [
                    'enable_synths' => false,
                ],
            ],
        ]);
    });

    // Register events dispatcher
    $app->singleton('events', function ($app) {
        return new \Illuminate\Events\Dispatcher($app);
    });

    // Make config() helper available
    if (!function_exists('config')) {
        function config($key = null, $default = null) {
            $config = \Illuminate\Container\Container::getInstance()->make('config');
            if (is_null($key)) {
                return $config;
            }
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $config->set($k, $v);
                }
                return null;
            }
            return $config->get($key, $default);
        }
    }

    // Make app() helper available
    if (!function_exists('app')) {
        function app($abstract = null, array $parameters = []) {
            $container = \Illuminate\Container\Container::getInstance();
            if (is_null($abstract)) {
                return $container;
            }
            return $container->make($abstract, $parameters);
        }
    }

    define('LARAVEL_DATA_AVAILABLE', true);
} else {
    define('LARAVEL_DATA_AVAILABLE', false);
}

/**
 * Benchmark helper function.
 *
 * @param string $name
 * @param callable $callback
 * @param int $iterations
 * @return array{name: string, iterations: int, total_time: float, avg_time: float, ops_per_sec: float, memory_peak: int}
 */
function benchmark(string $name, callable $callback, int $iterations = 10000): array
{
    // Warmup
    for ($i = 0; $i < min(100, $iterations / 10); $i++) {
        $callback();
    }

    gc_collect_cycles();
    $startMemory = memory_get_usage(true);

    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $callback();
    }
    $end = hrtime(true);

    $totalTime = ($end - $start) / 1e9; // Convert to seconds
    $avgTime = $totalTime / $iterations;
    $opsPerSec = $iterations / $totalTime;
    $memoryPeak = memory_get_peak_usage(true) - $startMemory;

    return [
        'name' => $name,
        'iterations' => $iterations,
        'total_time' => $totalTime,
        'avg_time' => $avgTime,
        'ops_per_sec' => $opsPerSec,
        'memory_peak' => $memoryPeak,
    ];
}

/**
 * Format benchmark results as a table row.
 *
 * @param array $result
 * @return string
 */
function formatResult(array $result): string
{
    return sprintf(
        '| %-40s | %10s | %12s | %15s | %10s |',
        $result['name'],
        number_format($result['iterations']),
        number_format($result['avg_time'] * 1e6, 2) . ' µs',
        number_format($result['ops_per_sec'], 0) . '/s',
        formatBytes($result['memory_peak']),
    );
}

/**
 * Format bytes to human readable.
 *
 * @param int $bytes
 * @return string
 */
function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes / (1024 * 1024), 1) . ' MB';
}

/**
 * Print table header.
 *
 * @return void
 */
function printHeader(): void
{
    echo str_repeat('-', 100) . "\n";
    printf(
        "| %-40s | %10s | %12s | %15s | %10s |\n",
        'Benchmark',
        'Iterations',
        'Avg Time',
        'Ops/sec',
        'Memory',
    );
    echo str_repeat('-', 100) . "\n";
}

/**
 * Print section header.
 *
 * @param string $title
 * @return void
 */
function printSection(string $title): void
{
    echo "\n" . str_repeat('=', 100) . "\n";
    echo "  {$title}\n";
    echo str_repeat('=', 100) . "\n";
    printHeader();
}
