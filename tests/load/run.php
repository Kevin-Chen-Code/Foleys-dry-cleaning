<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';
$options = getopt('', ['requests:', 'duration:', 'workers:', 'history:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php tests/load/run.php [--requests=600] [--duration=60] [--workers=8] [--history=0]\n";
    echo "Creates a NEW local SQLite database; never uses DATABASE_URL or app_local.php. Requires k6.\n";
    exit(0);
}
$server = null; $serverPid = null; $loadProcess = null;
$stop = static function () use (&$server, &$serverPid, &$loadProcess): void {
    if (is_resource($loadProcess)) {
        proc_terminate($loadProcess);
        proc_close($loadProcess);
        $loadProcess = null;
    }
    if (is_resource($server)) {
        // serve.php creates its own process group, containing only this run's workers.
        if ($serverPid && posix_getpgid($serverPid) === $serverPid) { posix_kill(-$serverPid, SIGTERM); }
        else { proc_terminate($server); }
        proc_close($server);
        $server = null;
    }
};
register_shutdown_function($stop);
try {
    if (PHP_SAPI !== 'cli' || !function_exists('posix_getpgid') || !function_exists('pcntl_exec') || !extension_loaded('pdo_sqlite') || !extension_loaded('curl')) {
        throw new RuntimeException('Requires macOS/Linux PHP CLI with POSIX/pcntl, pdo_sqlite and curl.');
    }
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use ($stop): void { $stop(); exit(130); });
    pcntl_signal(SIGTERM, static function () use ($stop): void { $stop(); exit(143); });
    $numbers = [];
    foreach (['requests' => [600, 1, 10000], 'duration' => [60, 1, 3600], 'workers' => [8, 2, 32], 'history' => [0, 0, 100000]] as $name => [$default, $min, $max]) {
        $value = $options[$name] ?? $default;
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < $min || (int)$value > $max) {
            throw new RuntimeException("Invalid --$name (allowed $min to $max).");
        }
        $numbers[$name] = (int)$value;
    }
    $candidates = [getenv('K6_BIN') ?: ''];
    foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $dir) { $candidates[] = $dir . '/k6'; }
    $candidates = array_merge($candidates, glob(FoleysLoadHarness::root() . '/tmp/load-tools/k6-*/k6') ?: []);
    $k6 = null;
    foreach ($candidates as $candidate) { if ($candidate !== '' && is_file($candidate) && is_executable($candidate)) { $k6 = realpath($candidate); break; } }
    if (!$k6) { throw new RuntimeException('k6 not found. Install with brew install k6, or set K6_BIN to its executable path.'); }
    $m = FoleysLoadHarness::create($numbers['requests'], $numbers['duration'], $numbers['workers'], $numbers['history']);
    putenv('FOLEYS_LOAD_MANIFEST=' . $m['directory'] . '/manifest.json');
    require FoleysLoadHarness::root() . '/vendor/autoload.php';
    require __DIR__ . '/config/bootstrap.php';
    // The isolated bootstrap deliberately removes its local variables.
    $m = FoleysLoadHarness::manifest((string)getenv('FOLEYS_LOAD_MANIFEST'));
    FoleysLoadHarness::seed($m);
    echo "ISOLATED LOCAL TEST: SQLite, non-sending mail; no live DB or production settings loaded.\n";
    echo "Run: {$m['runId']}; {$m['requests']} submissions over {$m['duration']} seconds; {$m['workers']} PHP workers; {$m['history']} synthetic historical orders.\n";
    echo "Artifacts: {$m['directory']}\n";
    $server = proc_open([PHP_BINARY, __DIR__ . '/serve.php', $m['directory'] . '/manifest.json'], [0 => ['pipe', 'r'], 1 => ['file', $m['directory'] . '/server.log', 'a'], 2 => ['file', $m['directory'] . '/server.log', 'a']], $pipes);
    if (!is_resource($server)) { throw new RuntimeException('Cannot start isolated server.'); }
    fclose($pipes[0]);
    $serverPid = proc_get_status($server)['pid'];
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $curl = curl_init($m['baseUrl'] . '/__loadtest/health');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 500, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => ['X-Load-Test-Token: ' . $m['token']]]);
        $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $health = is_string($body) ? json_decode($body, true) : null;
        if ($status === 200 && ($health['runId'] ?? '') === $m['runId'] && ($health['emails'] ?? '') === 'non-sending') { $ready = true; break; }
        usleep(100000);
    }
    if (!$ready) { throw new RuntimeException('Server did not start; see ' . $m['directory'] . '/server.log'); }
    $k6Environment = [
        'PATH' => getenv('PATH') ?: '', 'FOLEYS_LOAD_MANIFEST' => $m['directory'] . '/manifest.json',
        'K6_NO_USAGE_REPORT' => 'true', 'K6_WEB_DASHBOARD' => 'false', 'NO_PROXY' => '127.0.0.1',
    ];
    $loadProcess = proc_open([$k6, 'run', '--no-usage-report', '--quiet', '--config', $m['directory'] . '/k6-config.json', __DIR__ . '/submit-requests.js'], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, null, $k6Environment);
    if (!is_resource($loadProcess)) { throw new RuntimeException('Cannot start k6.'); }
    $exit = proc_close($loadProcess);
    $loadProcess = null;
    $stop();
    $report = FoleysLoadHarness::reconcile($m);
    $summaryFile = $m['directory'] . '/k6-summary.json';
    $summary = is_file($summaryFile) ? json_decode((string)file_get_contents($summaryFile), true, 512, JSON_THROW_ON_ERROR) : [];
    $report['k6ExitCode'] = $exit;
    $report['submissionP95Ms'] = $summary['metrics']['submission_duration']['values']['p(95)'] ?? null;
    $report['submissionMaxMs'] = $summary['metrics']['submission_duration']['values']['max'] ?? null;
    $report['droppedIterations'] = $summary['metrics']['dropped_iterations']['values']['count'] ?? null;
    $report['peakVirtualUsers'] = $summary['metrics']['vus']['values']['max'] ?? null;
    $report['allChecksPassed'] = $report['passed'] && $exit === 0;
    file_put_contents($m['directory'] . '/verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit($report['allChecksPassed'] ? 0 : 1);
} catch (Throwable $error) {
    $stop();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
