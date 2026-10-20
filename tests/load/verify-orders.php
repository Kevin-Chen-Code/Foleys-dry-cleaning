<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';
try {
    $manifest = FoleysLoadHarness::manifest($argv[1] ?? '');
    $report = FoleysLoadHarness::reconcile($manifest);
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit($report['passed'] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
