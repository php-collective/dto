<?php

declare(strict_types=1);

/**
 * Lazy field performance benchmark.
 *
 * Tests the performance impact of lazy field handling.
 */

require_once __DIR__ . '/bootstrap.php';

// Autoload benchmark classes
spl_autoload_register(function (string $class) {
    $prefix = 'Benchmark\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

$iterations = 10000;

// Test data
$simpleUserData = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'active' => true,
    'roles' => ['admin', 'user'],
];

$complexOrderData = [
    'id' => 1001,
    'customer' => $simpleUserData,
    'shippingAddress' => [
        'street' => '123 Main St',
        'city' => 'New York',
        'country' => 'USA',
        'zipCode' => '10001',
    ],
    'items' => [
        ['productId' => 1, 'name' => 'Widget', 'quantity' => 2, 'price' => 29.99],
        ['productId' => 2, 'name' => 'Gadget', 'quantity' => 1, 'price' => 49.99],
    ],
    'total' => 169.94,
    'status' => 'pending',
];

$results = [];

// ============================================================================
// Non-lazy DTOs - Baseline
// ============================================================================

printSection('Non-lazy DTOs (baseline - existing benchmark DTOs)');

$results[] = $r = benchmark('UserDto creation', function () use ($simpleUserData) {
    return new \Benchmark\Dto\UserDto($simpleUserData);
}, $iterations);
echo formatResult($r) . "\n";

$user = new \Benchmark\Dto\UserDto($simpleUserData);
$results[] = $r = benchmark('UserDto getters (6 calls)', function () use ($user) {
    $a = $user->getId();
    $b = $user->getName();
    $c = $user->getEmail();
    $d = $user->getPhone();
    $e = $user->getActive();
    $f = $user->getRoles();
    return [$a, $b, $c, $d, $e, $f];
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('UserDto toArray()', function () use ($user) {
    return $user->toArray();
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('OrderDto nested creation', function () use ($complexOrderData) {
    return new \Benchmark\Dto\OrderDto($complexOrderData);
}, $iterations);
echo formatResult($r) . "\n";

$order = new \Benchmark\Dto\OrderDto($complexOrderData);
$results[] = $r = benchmark('OrderDto toArray()', function () use ($order) {
    return $order->toArray();
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('OrderDto touchedToArray()', function () use ($order) {
    return $order->touchedToArray();
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Lazy Field Operations - Using Test DTOs
// ============================================================================

// Load test DTOs
require_once dirname(__DIR__) . '/tests/TestDto/LazyDto.php';
require_once dirname(__DIR__) . '/tests/TestDto/SimpleDto.php';

printSection('Lazy Field Operations (LazyDto test class)');

$lazyData = [
    'title' => 'Test Title',
    'nested' => ['name' => 'test', 'count' => 42],
    'items' => [
        ['name' => 'Item1', 'count' => 1],
        ['name' => 'Item2', 'count' => 2],
    ],
];

// Creation with lazy fields (no hydration)
$results[] = $r = benchmark('LazyDto creation (raw data)', function () use ($lazyData) {
    return new \PhpCollective\Dto\Test\TestDto\LazyDto($lazyData);
}, $iterations);
echo formatResult($r) . "\n";

// Getter (triggers hydration first time, then cached)
$lazyDto = new \PhpCollective\Dto\Test\TestDto\LazyDto($lazyData);
$results[] = $r = benchmark('LazyDto getNested() (after first hydration)', function () use ($lazyDto) {
    return $lazyDto->getNested();
}, $iterations);
echo formatResult($r) . "\n";

// Fresh instance each time to measure hydration
$results[] = $r = benchmark('LazyDto getNested() (fresh hydration each time)', function () use ($lazyData) {
    $dto = new \PhpCollective\Dto\Test\TestDto\LazyDto($lazyData);
    return $dto->getNested();
}, $iterations);
echo formatResult($r) . "\n";

// Collection hydration
$results[] = $r = benchmark('LazyDto getItems() collection hydration', function () use ($lazyData) {
    $dto = new \PhpCollective\Dto\Test\TestDto\LazyDto($lazyData);
    return $dto->getItems();
}, $iterations);
echo formatResult($r) . "\n";

// Test null lazy field detection
$lazyDtoWithNull = new \PhpCollective\Dto\Test\TestDto\LazyDto(['title' => 'Test', 'nested' => null]);
$results[] = $r = benchmark('LazyDto getNested() (null value)', function () use ($lazyDtoWithNull) {
    return $lazyDtoWithNull->getNested();
}, $iterations);
echo formatResult($r) . "\n";

// toArray without hydration
$lazyDto2 = new \PhpCollective\Dto\Test\TestDto\LazyDto($lazyData);
$results[] = $r = benchmark('LazyDto toArray() (no prior hydration)', function () use ($lazyDto2) {
    return $lazyDto2->toArray();
}, $iterations);
echo formatResult($r) . "\n";

// Clone performance
$results[] = $r = benchmark('LazyDto clone()', function () use ($lazyDto) {
    return $lazyDto->clone();
}, $iterations);
echo formatResult($r) . "\n";

// touchedToArray
$results[] = $r = benchmark('LazyDto touchedToArray()', function () use ($lazyDto) {
    return $lazyDto->touchedToArray();
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Summary
// ============================================================================

echo "\n" . str_repeat('=', 100) . "\n";
echo "  LAZY BENCHMARK COMPLETE\n";
echo str_repeat('=', 100) . "\n";
echo "\nIterations per test: " . number_format($iterations) . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
