<?php
declare(strict_types=1);

require_once __DIR__ . '/harness.php';
$m = FoleysLoadHarness::manifest((string)getenv('FOLEYS_LOAD_MANIFEST'));
// Neither a query parameter nor an environment variable can activate this in the real app.
if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || ($_SERVER['HTTP_HOST'] ?? '') !== substr($m['baseUrl'], 7)
    || !hash_equals($m['token'], $_SERVER['HTTP_X_LOAD_TEST_TOKEN'] ?? '')) {
    http_response_code(403);
    exit('Isolated localhost load-test access only.');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('X-Foleys-Load-Test: ' . $m['runId']);
if ($path === '/__loadtest/health') {
    header('Content-Type: application/json');
    echo json_encode(['mode' => $m['mode'], 'runId' => $m['runId'], 'database' => 'sqlite', 'emails' => 'non-sending']);
    return;
}
if (!in_array($path, ['/request', '/request/received'], true)) {
    http_response_code(404);
    exit('Only request submission routes are available in this isolated server.');
}
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$server = new \Cake\Http\Server(new \App\Application(__DIR__ . '/config'));
$server->emit($server->run());
