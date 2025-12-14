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
