<?php

declare(strict_types=1);

/**
 * DTO Benchmark Suite
 *
 * Compares php-collective/dto against:
 * - Plain PHP readonly DTOs
 * - Plain nested arrays
 * - Other DTO libraries (if installed)
 *
 * Usage: php benchmark/run.php [--iterations=N] [--json]
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

// Parse arguments
$iterations = 10000;
$jsonOutput = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--iterations=')) {
        $iterations = (int)substr($arg, 13);
    }
    if ($arg === '--json') {
        $jsonOutput = true;
    }
}

// Test data
$simpleUserData = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'active' => true,
    'roles' => ['admin', 'user'],
];

$addressData = [
    'street' => '123 Main St',
    'city' => 'New York',
    'country' => 'USA',
    'zipCode' => '10001',
];

$orderItemsData = [
    ['productId' => 1, 'name' => 'Widget', 'quantity' => 2, 'price' => 29.99],
    ['productId' => 2, 'name' => 'Gadget', 'quantity' => 1, 'price' => 49.99],
    ['productId' => 3, 'name' => 'Gizmo', 'quantity' => 3, 'price' => 19.99],
];

$complexOrderData = [
    'id' => 1001,
    'customer' => $simpleUserData,
    'shippingAddress' => $addressData,
    'items' => $orderItemsData,
    'total' => 169.94,
    'status' => 'pending',
    'createdAt' => '2024-01-15 10:30:00',
];

$results = [];

// ============================================================================
// Section 1: Simple DTO Creation
// ============================================================================

printSection('1. Simple DTO Creation (User with 6 fields)');

// Plain PHP readonly DTO
$results[] = $r = benchmark('Plain PHP readonly DTO', function () use ($simpleUserData) {
    return new \Benchmark\PlainDto\UserDto(
        id: $simpleUserData['id'],
        name: $simpleUserData['name'],
        email: $simpleUserData['email'],
        phone: $simpleUserData['phone'],
        active: $simpleUserData['active'],
        roles: $simpleUserData['roles'],
    );
}, $iterations);
echo formatResult($r) . "\n";

// Plain PHP fromArray
$results[] = $r = benchmark('Plain PHP DTO::fromArray()', function () use ($simpleUserData) {
    return \Benchmark\PlainDto\UserDto::fromArray($simpleUserData);
}, $iterations);
echo formatResult($r) . "\n";

// php-collective/dto
$results[] = $r = benchmark('php-collective/dto new()', function () use ($simpleUserData) {
    return new \Benchmark\Generated\Dto\UserDto($simpleUserData);
}, $iterations);
echo formatResult($r) . "\n";

// php-collective/dto createFromArray
$results[] = $r = benchmark('php-collective/dto createFromArray()', function () use ($simpleUserData) {
    return \Benchmark\Generated\Dto\UserDto::createFromArray($simpleUserData);
}, $iterations);
echo formatResult($r) . "\n";

// Plain nested array (baseline)
$results[] = $r = benchmark('Plain nested array (baseline)', function () use ($simpleUserData) {
    return $simpleUserData;
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Section 2: Complex Nested DTO Creation
// ============================================================================

printSection('2. Complex Nested DTO Creation (Order with User, Address, Items)');

// Plain PHP nested DTOs
$results[] = $r = benchmark('Plain PHP nested DTOs', function () use ($complexOrderData) {
    return \Benchmark\PlainDto\OrderDto::fromArray($complexOrderData);
}, $iterations);
echo formatResult($r) . "\n";

// php-collective/dto nested
$results[] = $r = benchmark('php-collective/dto nested', function () use ($complexOrderData) {
    return new \Benchmark\Generated\Dto\OrderDto($complexOrderData);
}, $iterations);
echo formatResult($r) . "\n";

// Plain nested array
$results[] = $r = benchmark('Plain nested array', function () use ($complexOrderData) {
    return $complexOrderData;
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Section 3: Property Access
// ============================================================================

printSection('3. Property Access (10 reads)');

$plainUser = \Benchmark\PlainDto\UserDto::fromArray($simpleUserData);
$generatedUser = new \Benchmark\Generated\Dto\UserDto($simpleUserData);

$results[] = $r = benchmark('Plain PHP property access', function () use ($plainUser) {
    $a = $plainUser->id;
    $b = $plainUser->name;
    $c = $plainUser->email;
    $d = $plainUser->phone;
    $e = $plainUser->active;
    $f = $plainUser->roles;
    $g = $plainUser->id;
    $h = $plainUser->name;
    $i = $plainUser->email;
    $j = $plainUser->phone;

    return [$a, $b, $c, $d, $e, $f, $g, $h, $i, $j];
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('php-collective/dto getters', function () use ($generatedUser) {
    $a = $generatedUser->getId();
    $b = $generatedUser->getName();
    $c = $generatedUser->getEmail();
    $d = $generatedUser->getPhone();
    $e = $generatedUser->getActive();
    $f = $generatedUser->getRoles();
    $g = $generatedUser->getId();
    $h = $generatedUser->getName();
    $i = $generatedUser->getEmail();
    $j = $generatedUser->getPhone();

    return [$a, $b, $c, $d, $e, $f, $g, $h, $i, $j];
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('Plain array access', function () use ($simpleUserData) {
    $a = $simpleUserData['id'];
    $b = $simpleUserData['name'];
    $c = $simpleUserData['email'];
    $d = $simpleUserData['phone'];
    $e = $simpleUserData['active'];
    $f = $simpleUserData['roles'];
    $g = $simpleUserData['id'];
    $h = $simpleUserData['name'];
    $i = $simpleUserData['email'];
    $j = $simpleUserData['phone'];

    return [$a, $b, $c, $d, $e, $f, $g, $h, $i, $j];
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Section 4: Serialization (toArray)
// ============================================================================

printSection('4. Serialization - toArray()');

$plainOrder = \Benchmark\PlainDto\OrderDto::fromArray($complexOrderData);
$generatedOrder = new \Benchmark\Generated\Dto\OrderDto($complexOrderData);

$results[] = $r = benchmark('Plain PHP toArray()', function () use ($plainOrder) {
    return $plainOrder->toArray();
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('php-collective/dto toArray()', function () use ($generatedOrder) {
    return $generatedOrder->toArray();
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('Plain array (no conversion)', function () use ($complexOrderData) {
    return $complexOrderData;
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Section 5: JSON Serialization
// ============================================================================

printSection('5. JSON Serialization - json_encode()');

$results[] = $r = benchmark('Plain PHP DTO -> JSON', function () use ($plainOrder) {
    return json_encode($plainOrder->toArray());
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('php-collective/dto -> JSON', function () use ($generatedOrder) {
    return json_encode($generatedOrder->toArray());
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('Plain array -> JSON', function () use ($complexOrderData) {
    return json_encode($complexOrderData);
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Section 6: Template Simulation (accessing nested properties)
// ============================================================================

printSection('6. Template Simulation (render order summary)');

$results[] = $r = benchmark('Plain PHP DTO template render', function () use ($plainOrder) {
    $output = "Order #{$plainOrder->id}\n";
    $output .= "Customer: {$plainOrder->customer->name} ({$plainOrder->customer->email})\n";
    $output .= "Ship to: {$plainOrder->shippingAddress->street}, {$plainOrder->shippingAddress->city}\n";
    $output .= "Items:\n";
    foreach ($plainOrder->items as $item) {
        $output .= "  - {$item->name} x{$item->quantity} @ \${$item->price}\n";
    }
    $output .= "Total: \${$plainOrder->total}\n";

    return $output;
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('php-collective/dto template render', function () use ($generatedOrder) {
    $output = "Order #{$generatedOrder->getId()}\n";
    $output .= "Customer: {$generatedOrder->getCustomer()->getName()} ({$generatedOrder->getCustomer()->getEmail()})\n";
    $output .= "Ship to: {$generatedOrder->getShippingAddress()->getStreet()}, {$generatedOrder->getShippingAddress()->getCity()}\n";
    $output .= "Items:\n";
    foreach ($generatedOrder->getItems() as $item) {
        $output .= "  - {$item->getName()} x{$item->getQuantity()} @ \${$item->getPrice()}\n";
    }
    $output .= "Total: \${$generatedOrder->getTotal()}\n";

    return $output;
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('Plain array template render', function () use ($complexOrderData) {
    $output = "Order #{$complexOrderData['id']}\n";
    $output .= "Customer: {$complexOrderData['customer']['name']} ({$complexOrderData['customer']['email']})\n";
    $output .= "Ship to: {$complexOrderData['shippingAddress']['street']}, {$complexOrderData['shippingAddress']['city']}\n";
    $output .= "Items:\n";
    foreach ($complexOrderData['items'] as $item) {
        $output .= "  - {$item['name']} x{$item['quantity']} @ \${$item['price']}\n";
    }
    $output .= "Total: \${$complexOrderData['total']}\n";

    return $output;
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Section 7: Mutable vs Immutable
// ============================================================================

printSection('7. Mutable vs Immutable Operations');

$mutableUser = new \Benchmark\Generated\Dto\UserDto($simpleUserData);
$immutableUserData = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'active' => true,
];
$immutableUser = new \Benchmark\Generated\Dto\ImmutableUserDto($immutableUserData);

$results[] = $r = benchmark('Mutable DTO: setName()', function () use ($mutableUser) {
    $mutableUser->setName('Jane Doe');

    return $mutableUser;
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('Immutable DTO: withName()', function () use ($immutableUser) {
    return $immutableUser->withName('Jane Doe');
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Section 8: Collection Operations
// ============================================================================

printSection('8. Collection Operations');

$results[] = $r = benchmark('php-collective/dto addItem()', function () use ($generatedOrder) {
    $order = new \Benchmark\Generated\Dto\OrderDto([
        'id' => 1,
        'customer' => $generatedOrder->getCustomer(),
        'shippingAddress' => $generatedOrder->getShippingAddress(),
        'total' => 0,
        'status' => 'new',
    ]);
    $order->addItem(new \Benchmark\Generated\Dto\OrderItemDto([
        'productId' => 99,
        'name' => 'Test',
        'quantity' => 1,
        'price' => 9.99,
    ]));

    return $order;
}, $iterations);
echo formatResult($r) . "\n";

$results[] = $r = benchmark('Plain array append', function () use ($complexOrderData) {
    $order = $complexOrderData;
    $order['items'] = [];
    $order['items'][] = [
        'productId' => 99,
        'name' => 'Test',
        'quantity' => 1,
        'price' => 9.99,
    ];

    return $order;
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Summary
// ============================================================================

echo "\n" . str_repeat('=', 100) . "\n";
echo "  BENCHMARK COMPLETE\n";
echo str_repeat('=', 100) . "\n";
echo "\nIterations per test: " . number_format($iterations) . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";

if ($jsonOutput) {
    echo "\n--- JSON Results ---\n";
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
}
