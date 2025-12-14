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
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
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

// Test data
$simpleUserData = [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
    'active' => true,
    'roles' => ['admin', 'user'],
];

$results = [];

echo "\n" . str_repeat('=', 100) . "\n";
echo "  External Library Comparison\n";
echo str_repeat('=', 100) . "\n";
echo "\nChecking available libraries...\n\n";

// ============================================================================
// Check library availability
// ============================================================================

$libraries = [
    'spatie/data-transfer-object' => class_exists(\Spatie\DataTransferObject\DataTransferObject::class),
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
// CuyZ/Valinor
// ============================================================================

if ($libraries['cuyz/valinor']) {
    printSection('CuyZ/Valinor');

    // Valinor requires a mapper - expensive to create, so we create once
    $mapper = (new \CuyZ\Valinor\MapperBuilder())
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

    $results['valinor'] = $r = benchmark('Valinor map()', function () use ($mapper, $simpleUserData, $valinorClass) {
        return $mapper->map($valinorClass, $simpleUserData);
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
