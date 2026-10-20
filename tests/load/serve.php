<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';
if (PHP_SAPI !== 'cli' || !function_exists('posix_setsid') || !function_exists('pcntl_exec')) {
    throw new RuntimeException('The isolated server requires PHP CLI with POSIX/pcntl (macOS or Linux).');
}
$m = FoleysLoadHarness::manifest($argv[1] ?? '');
if (posix_setsid() === -1) { throw new RuntimeException('Cannot create isolated server process group.'); }
putenv('FOLEYS_LOAD_MANIFEST=' . $m['directory'] . '/manifest.json');
putenv('PHP_CLI_SERVER_WORKERS=' . $m['workers']);
pcntl_exec(PHP_BINARY, ['-S', substr($m['baseUrl'], 7), '-t', FoleysLoadHarness::root() . '/webroot', __DIR__ . '/router.php']);
throw new RuntimeException('Cannot launch the isolated PHP server.');
