<?php
declare(strict_types=1);

// Uso: php tests/run.php
require __DIR__ . '/harness.php';
require __DIR__ . '/fixtures.php';

foreach (glob(__DIR__ . '/*.test.php') ?: [] as $file) {
    require $file;
}
exit(runTests());
