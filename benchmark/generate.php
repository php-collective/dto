<?php

declare(strict_types=1);

/**
 * Generates the php-collective/dto benchmark DTOs with strictTypes enabled.
 *
 * The bin/dto CLI does not expose strictTypes, and transform inlining requires it, so this
 * drives the generator API directly.
 *
 * Usage: php benchmark/generate.php
 */

use PhpCollective\Dto\Engine\PhpEngine;
use PhpCollective\Dto\Generator\ArrayConfig;
use PhpCollective\Dto\Generator\Builder;
use PhpCollective\Dto\Generator\ConsoleIo;
use PhpCollective\Dto\Generator\Generator;
use PhpCollective\Dto\Generator\TwigRenderer;

require __DIR__ . '/vendor/autoload.php';

$config = new ArrayConfig([
    'namespace' => 'Benchmark',
    'strictTypes' => true,
]);

$engine = new PhpEngine();
$builder = new Builder($engine, $config);
$renderer = new TwigRenderer(null, $config);
$io = new ConsoleIo();

$generator = new Generator($builder, $renderer, $io, $config);

exit($generator->generate(__DIR__ . '/config/', __DIR__ . '/src/', [
    'force' => true,
    'dryRun' => false,
    'confirm' => true,
]));
