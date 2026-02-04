<?php

declare(strict_types=1);

/**
 * External Library Comparison Benchmarks
 *
 * Compares php-collective/dto against other DTO libraries.
 * Libraries that aren't installed will be skipped.
 *
 * Usage: php benchmark/run-external.php [--iterations=N]
 */

// Conditional use statements moved here (only used if library is installed)
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--iterations=')) {
        $iterations = (int)substr($arg, 13);
    }
}

// Test data - Simple
$simpleUserData = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'active' => true,
    'roles' => ['admin', 'user'],
];

// Test data - Complex nested
$complexOrderData = [
    'id' => 1001,
    'customer' => [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'active' => true,
        'roles' => ['customer'],
    ],
    'shippingAddress' => [
        'street' => '123 Main St',
        'city' => 'Springfield',
        'country' => 'USA',
        'zipCode' => '12345',
    ],
    'items' => [
        ['productId' => 1, 'name' => 'Widget A', 'quantity' => 2, 'price' => 29.99],
        ['productId' => 2, 'name' => 'Widget B', 'quantity' => 1, 'price' => 49.99],
        ['productId' => 3, 'name' => 'Widget C', 'quantity' => 3, 'price' => 19.99],
    ],
    'total' => 169.94,
    'status' => 'pending',
];

$results = [];
$nestedResults = [];
$readResults = [];
$realisticResults = [];

echo "\n" . str_repeat('=', 100) . "\n";
echo "  External Library Comparison\n";
echo str_repeat('=', 100) . "\n";
echo "\nChecking available libraries...\n\n";

// ============================================================================
// Check library availability
// ============================================================================

$libraries = [
    'spatie/data-transfer-object' => class_exists(\Spatie\DataTransferObject\DataTransferObject::class),
    'spatie/laravel-data' => class_exists(\Spatie\LaravelData\Data::class),
    'cuyz/valinor' => class_exists(\CuyZ\Valinor\MapperBuilder::class),
    'symfony/serializer' => class_exists(\Symfony\Component\Serializer\Serializer::class),
    'jms/serializer' => class_exists(\JMS\Serializer\SerializerBuilder::class),
];

foreach ($libraries as $lib => $available) {
    $status = $available ? '✓ Available' : '✗ Not installed';
    echo "  {$lib}: {$status}\n";
}

echo "\n";

// ============================================================================
// php-collective/dto (baseline for comparison)
// ============================================================================

printSection('php-collective/dto (Reference)');

$results['php-collective/dto'] = $r = benchmark('php-collective/dto createFromArray()', function () use ($simpleUserData) {
    return \Benchmark\Dto\UserDto::createFromArray($simpleUserData);
}, $iterations);
echo formatResult($r) . "\n";

// ============================================================================
// Spatie Data Transfer Object
// ============================================================================

if ($libraries['spatie/data-transfer-object']) {
    printSection('Spatie Data Transfer Object');

    // Define a DTO class extending Spatie's base
    $spatieUserClass = new class ($simpleUserData) extends \Spatie\DataTransferObject\DataTransferObject {
        public int $id;
        public string $name;
        public string $email;
        public ?string $phone;
        public bool $active;
        /** @var string[] */
        public array $roles;
    };

    $spatieClass = get_class($spatieUserClass);

    $results['spatie-dto'] = $r = benchmark('Spatie DTO new()', function () use ($simpleUserData, $spatieClass) {
        return new $spatieClass($simpleUserData);
    }, $iterations);
    echo formatResult($r) . "\n";
}

// ============================================================================
// Spatie Laravel Data
// ============================================================================

if ($libraries['spatie/laravel-data']) {
    printSection('Spatie Laravel Data');
    echo "  ⚠ Requires Laravel framework - cannot benchmark standalone\n";
    echo "  See: https://spatie.be/docs/laravel-data\n\n";
}

// ============================================================================
// CuyZ/Valinor
// ============================================================================

if ($libraries['cuyz/valinor']) {
    printSection('CuyZ/Valinor');

    // Valinor requires a mapper - expensive to create, so we create once
    $valinorMapper = (new \CuyZ\Valinor\MapperBuilder())
        ->allowPermissiveTypes()
        ->mapper();

    // Define a simple target class for Valinor
    $valinorUserClass = new class (0, '', '', null, true, []) {
        public function __construct(
            public int $id,
            public string $name,
            public string $email,
            public ?string $phone,
            public bool $active,
            /** @var array<string> */
            public array $roles,
        ) {
        }
    };

    $valinorClass = get_class($valinorUserClass);

    $results['valinor'] = $r = benchmark('Valinor map()', function () use ($valinorMapper, $simpleUserData, $valinorClass) {
        return $valinorMapper->map($valinorClass, $simpleUserData);
    }, $iterations);
    echo formatResult($r) . "\n";

    // Including mapper creation
    $results['valinor-with-setup'] = $r = benchmark('Valinor (with mapper setup)', function () use ($simpleUserData, $valinorClass) {
        $mapper = (new \CuyZ\Valinor\MapperBuilder())->mapper();

        return $mapper->map($valinorClass, $simpleUserData);
    }, min(1000, $iterations / 10));
    echo formatResult($r) . "\n";
}

// ============================================================================
// Symfony Serializer
// ============================================================================

if ($libraries['symfony/serializer']) {
    printSection('Symfony Serializer');

    // Create serializer once
    $serializer = new Serializer(
        [new ObjectNormalizer(), new ArrayDenormalizer()],
        [new JsonEncoder()]
    );

    // Define target class
    $symfonyUserClass = new class (0, '', '', null, true, []) {
        public function __construct(
            public int $id = 0,
            public string $name = '',
            public string $email = '',
            public ?string $phone = null,
            public bool $active = true,
            public array $roles = [],
        ) {
        }
    };

    $symfonyClass = get_class($symfonyUserClass);
    $jsonData = json_encode($simpleUserData);

    $results['symfony'] = $r = benchmark('Symfony deserialize()', function () use ($serializer, $jsonData, $symfonyClass) {
        return $serializer->deserialize($jsonData, $symfonyClass, 'json');
    }, $iterations);
    echo formatResult($r) . "\n";

    $results['symfony-denormalize'] = $r = benchmark('Symfony denormalize()', function () use ($serializer, $simpleUserData, $symfonyClass) {
        return $serializer->denormalize($simpleUserData, $symfonyClass);
    }, $iterations);
    echo formatResult($r) . "\n";
}

// ============================================================================
// JMS Serializer
// ============================================================================

if ($libraries['jms/serializer']) {
    printSection('JMS Serializer');

    $jmsSerializer = \JMS\Serializer\SerializerBuilder::create()->build();

    // JMS requires annotations or attributes, use a simple stdClass approach
    $jsonData = json_encode($simpleUserData);

    $results['jms'] = $r = benchmark('JMS deserialize() to array', function () use ($jmsSerializer, $jsonData) {
        return $jmsSerializer->deserialize($jsonData, 'array', 'json');
    }, $iterations);
    echo formatResult($r) . "\n";

    // With builder
    $results['jms-with-setup'] = $r = benchmark('JMS (with builder setup)', function () use ($jsonData) {
        $serializer = \JMS\Serializer\SerializerBuilder::create()->build();

        return $serializer->deserialize($jsonData, 'array', 'json');
    }, min(1000, $iterations / 10));
    echo formatResult($r) . "\n";
}

// ============================================================================
// PART 2: Complex Nested DTO Creation
// ============================================================================

echo "\n" . str_repeat('=', 100) . "\n";
echo "  PART 2: Complex Nested DTO Creation\n";
echo str_repeat('=', 100) . "\n";

printSection('php-collective/dto - Nested');

$nestedResults['php-collective/dto-nested'] = $r = benchmark('php-collective/dto OrderDto', function () use ($complexOrderData) {
    return \Benchmark\Dto\OrderDto::createFromArray($complexOrderData);
}, $iterations);
echo formatResult($r) . "\n";

if ($libraries['spatie/data-transfer-object']) {
    printSection('Spatie DTO - Nested');

    if (!class_exists('BenchmarkExternalSpatieCustomerDto')) {
        class BenchmarkExternalSpatieCustomerDto extends \Spatie\DataTransferObject\DataTransferObject
        {
            public int $id;
            public string $name;
            public string $email;
            public ?string $phone;
            public bool $active;
            /** @var string[] */
            public array $roles;
        }

        class BenchmarkExternalSpatieAddressDto extends \Spatie\DataTransferObject\DataTransferObject
        {
            public string $street;
            public string $city;
            public string $country;
            public string $zipCode;
        }

        class BenchmarkExternalSpatieOrderItemDto extends \Spatie\DataTransferObject\DataTransferObject
        {
            public int $productId;
            public string $name;
            public int $quantity;
            public float $price;
        }

        class BenchmarkExternalSpatieOrderDto extends \Spatie\DataTransferObject\DataTransferObject
        {
            public int $id;
            public BenchmarkExternalSpatieCustomerDto $customer;
            public BenchmarkExternalSpatieAddressDto $shippingAddress;

            #[\Spatie\DataTransferObject\Attributes\CastWith(
                \Spatie\DataTransferObject\Casters\ArrayCaster::class,
                itemType: BenchmarkExternalSpatieOrderItemDto::class
            )]
            public array $items;

            public float $total;
            public string $status;
        }
    }

    $nestedResults['spatie-dto-nested'] = $r = benchmark('Spatie DTO OrderDto', function () use ($complexOrderData) {
        return new BenchmarkExternalSpatieOrderDto($complexOrderData);
    }, $iterations);
    echo formatResult($r) . "\n";
}

if ($libraries['cuyz/valinor']) {
    printSection('CuyZ/Valinor - Nested');

    if (!class_exists('BenchmarkExternalValinorCustomer')) {
        class BenchmarkExternalValinorCustomer
        {
            public function __construct(
                public int $id,
                public string $name,
                public string $email,
                public ?string $phone,
                public bool $active,
                /** @var array<string> */
                public array $roles,
            ) {
            }
        }

        class BenchmarkExternalValinorAddress
        {
            public function __construct(
                public string $street,
                public string $city,
                public string $country,
                public string $zipCode,
            ) {
            }
        }

        class BenchmarkExternalValinorOrderItem
        {
            public function __construct(
                public int $productId,
                public string $name,
                public int $quantity,
                public float $price,
            ) {
            }
        }

        class BenchmarkExternalValinorOrder
        {
            public function __construct(
                public int $id,
                public BenchmarkExternalValinorCustomer $customer,
                public BenchmarkExternalValinorAddress $shippingAddress,
                /** @var list<BenchmarkExternalValinorOrderItem> */
                public array $items,
                public float $total,
                public string $status,
            ) {
            }
        }
    }

    $valinorOrderClass = BenchmarkExternalValinorOrder::class;
    $nestedResults['valinor-nested'] = $r = benchmark('Valinor map() Order', function () use ($valinorMapper, $complexOrderData, $valinorOrderClass) {
        return $valinorMapper->map($valinorOrderClass, $complexOrderData);
    }, $iterations);
    echo formatResult($r) . "\n";
}

if ($libraries['symfony/serializer']) {
    printSection('Symfony Serializer - Nested');

    if (!class_exists('BenchmarkExternalSymfonyCustomer')) {
        class BenchmarkExternalSymfonyCustomer
        {
            public int $id = 0;
            public string $name = '';
            public string $email = '';
            public ?string $phone = null;
            public bool $active = true;
            /** @var list<string> */
            public array $roles = [];
        }

        class BenchmarkExternalSymfonyAddress
        {
            public string $street = '';
            public string $city = '';
            public string $country = '';
            public string $zipCode = '';
        }

        class BenchmarkExternalSymfonyOrderItem
        {
            public int $productId = 0;
            public string $name = '';
            public int $quantity = 0;
            public float $price = 0.0;
        }

        class BenchmarkExternalSymfonyOrder
        {
            public int $id = 0;
            public ?BenchmarkExternalSymfonyCustomer $customer = null;
            public ?BenchmarkExternalSymfonyAddress $shippingAddress = null;
            /** @var list<BenchmarkExternalSymfonyOrderItem> */
            public array $items = [];
            public float $total = 0.0;
            public string $status = '';
        }
    }

    $propertyInfo = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
    $nestedSerializer = new Serializer(
        [new ObjectNormalizer(null, null, null, $propertyInfo), new ArrayDenormalizer()],
        [new JsonEncoder()]
    );

    $symfonyOrderClass = BenchmarkExternalSymfonyOrder::class;
    $nestedResults['symfony-nested'] = $r = benchmark('Symfony denormalize() Order', function () use ($nestedSerializer, $complexOrderData, $symfonyOrderClass) {
        return $nestedSerializer->denormalize($complexOrderData, $symfonyOrderClass);
    }, $iterations);
    echo formatResult($r) . "\n";
}

if (!empty($nestedResults)) {
    $baseline = $nestedResults['php-collective/dto-nested']['ops_per_sec'] ?? null;
    if ($baseline) {
        echo "\nRelative performance (nested, higher is better):\n\n";
        printf("  %-40s %15s %15s\n", 'Library', 'Ops/sec', 'vs php-collective/dto');
        echo "  " . str_repeat('-', 70) . "\n";

        foreach ($nestedResults as $name => $result) {
            $relative = $result['ops_per_sec'] / $baseline;
            $relativeStr = sprintf('%.2fx', $relative);
            if ($relative < 1) {
                $relativeStr = sprintf('%.2fx slower', 1 / $relative);
            } elseif ($relative > 1) {
                $relativeStr = sprintf('%.2fx faster', $relative);
            } else {
                $relativeStr = 'baseline';
            }

            printf(
                "  %-40s %15s %15s\n",
                $name,
                number_format($result['ops_per_sec'], 0) . '/s',
                $relativeStr
            );
        }
        echo "\n";
    }
}

// ============================================================================
// PART 3: Read Operations (Property Access)
// ============================================================================

echo "\n" . str_repeat('=', 100) . "\n";
echo "  PART 3: Read Operations (10 property reads)\n";
echo str_repeat('=', 100) . "\n";

// Pre-create objects for read benchmarks
$generatedUser = \Benchmark\Dto\UserDto::createFromArray($simpleUserData);

printSection('php-collective/dto - Read');

$readResults['php-collective/dto-read'] = $r = benchmark('php-collective/dto getters (x10)', function () use ($generatedUser) {
    $id = $generatedUser->getId();
    $name = $generatedUser->getName();
    $email = $generatedUser->getEmail();
    $phone = $generatedUser->getPhone();
    $active = $generatedUser->getActive();
    $roles = $generatedUser->getRoles();
    $id2 = $generatedUser->getId();
    $name2 = $generatedUser->getName();
    $email2 = $generatedUser->getEmail();
    $phone2 = $generatedUser->getPhone();

    return $phone2;
}, $iterations);
echo formatResult($r) . "\n";

if ($libraries['spatie/data-transfer-object']) {
    $spatieUserClass = new class ($simpleUserData) extends \Spatie\DataTransferObject\DataTransferObject {
        public int $id;
        public string $name;
        public string $email;
        public ?string $phone;
        public bool $active;
        /** @var string[] */
        public array $roles;
    };

    printSection('Spatie DTO - Read');

    $readResults['spatie-dto-read'] = $r = benchmark('Spatie DTO property access (x10)', function () use ($spatieUserClass) {
        $id = $spatieUserClass->id;
        $name = $spatieUserClass->name;
        $email = $spatieUserClass->email;
        $phone = $spatieUserClass->phone;
        $active = $spatieUserClass->active;
        $roles = $spatieUserClass->roles;
        $id2 = $spatieUserClass->id;
        $name2 = $spatieUserClass->name;
        $email2 = $spatieUserClass->email;
        $phone2 = $spatieUserClass->phone;

        return $phone2;
    }, $iterations);
    echo formatResult($r) . "\n";
}

// ============================================================================
// PART 4: Realistic Scenario (1 Create + 10 Reads)
// ============================================================================

echo "\n" . str_repeat('=', 100) . "\n";
echo "  PART 4: Realistic Scenario (1 Create + 10 Reads)\n";
echo str_repeat('=', 100) . "\n";

printSection('php-collective/dto - Realistic');

$realisticResults['php-collective/dto-realistic'] = $r = benchmark('php-collective/dto 1 write + 10 reads', function () use ($simpleUserData) {
    // 1 Create
    $user = \Benchmark\Dto\UserDto::createFromArray($simpleUserData);

    // 10 Reads (simulating template/view usage)
    $id = $user->getId();
    $name = $user->getName();
    $email = $user->getEmail();
    $phone = $user->getPhone();
    $active = $user->getActive();
    $roles = $user->getRoles();
    $displayName = $user->getName() . ' <' . $user->getEmail() . '>';
    $isAdmin = in_array('admin', $user->getRoles() ?? []);
    $status = $user->getActive() ? 'Active' : 'Inactive';
    $contactInfo = $user->getEmail() . ' / ' . $user->getPhone();

    return $contactInfo;
}, $iterations);
echo formatResult($r) . "\n";

if ($libraries['spatie/data-transfer-object']) {
    $spatieClass = get_class(new class ($simpleUserData) extends \Spatie\DataTransferObject\DataTransferObject {
        public int $id;
        public string $name;
        public string $email;
        public ?string $phone;
        public bool $active;
        /** @var string[] */
        public array $roles;
    });

    printSection('Spatie DTO - Realistic');

    $realisticResults['spatie-dto-realistic'] = $r = benchmark('Spatie DTO 1 write + 10 reads', function () use ($simpleUserData, $spatieClass) {
        // 1 Create
        $user = new $spatieClass($simpleUserData);

        // 10 Reads
        $id = $user->id;
        $name = $user->name;
        $email = $user->email;
        $phone = $user->phone;
        $active = $user->active;
        $roles = $user->roles;
        $displayName = $user->name . ' <' . $user->email . '>';
        $isAdmin = in_array('admin', $user->roles ?? []);
        $status = $user->active ? 'Active' : 'Inactive';
        $contactInfo = $user->email . ' / ' . $user->phone;

        return $contactInfo;
    }, $iterations);
    echo formatResult($r) . "\n";
}

if ($libraries['symfony/serializer']) {
    $serializer = new Serializer(
        [new ObjectNormalizer(), new ArrayDenormalizer()],
        [new JsonEncoder()]
    );

    $symfonyClass = get_class(new class (0, '', '', null, true, []) {
        public function __construct(
            public int $id = 0,
            public string $name = '',
            public string $email = '',
            public ?string $phone = null,
            public bool $active = true,
            public array $roles = [],
        ) {
        }
    });

    printSection('Symfony Serializer - Realistic');

    $realisticResults['symfony-realistic'] = $r = benchmark('Symfony 1 write + 10 reads', function () use ($serializer, $simpleUserData, $symfonyClass) {
        // 1 Create
        $user = $serializer->denormalize($simpleUserData, $symfonyClass);

        // 10 Reads
        $id = $user->id;
        $name = $user->name;
        $email = $user->email;
        $phone = $user->phone;
        $active = $user->active;
        $roles = $user->roles;
        $displayName = $user->name . ' <' . $user->email . '>';
        $isAdmin = in_array('admin', $user->roles ?? []);
        $status = $user->active ? 'Active' : 'Inactive';
        $contactInfo = $user->email . ' / ' . $user->phone;

        return $contactInfo;
    }, $iterations);
    echo formatResult($r) . "\n";
}

// ============================================================================
// Summary Comparison
// ============================================================================

echo "\n" . str_repeat('=', 100) . "\n";
echo "  COMPARISON SUMMARY\n";
echo str_repeat('=', 100) . "\n\n";

if (!empty($results)) {
    $baseline = $results['php-collective/dto']['ops_per_sec'];

    echo "Relative performance (higher is better):\n\n";
    printf("  %-40s %15s %15s\n", 'Library', 'Ops/sec', 'vs php-collective/dto');
    echo "  " . str_repeat('-', 70) . "\n";

    foreach ($results as $name => $result) {
        $relative = $result['ops_per_sec'] / $baseline;
        $relativeStr = sprintf('%.2fx', $relative);
        if ($relative < 1) {
            $relativeStr = sprintf('%.2fx slower', 1 / $relative);
        } elseif ($relative > 1) {
            $relativeStr = sprintf('%.2fx faster', $relative);
        } else {
            $relativeStr = 'baseline';
        }

        printf(
            "  %-40s %15s %15s\n",
            $name,
            number_format($result['ops_per_sec'], 0) . '/s',
            $relativeStr
        );
    }
}

echo "\n\nIterations per test: " . number_format($iterations) . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
