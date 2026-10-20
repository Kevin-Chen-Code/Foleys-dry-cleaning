<?php
declare(strict_types=1);

namespace App\Test\TestCase\Load;

use FoleysLoadHarness;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/load/harness.php';

final class LoadHarnessTest extends TestCase
{
    public function testRejectsNonGeneratedManifest(): void
    {
        $this->expectException(RuntimeException::class);
        FoleysLoadHarness::manifest(__FILE__);
    }

    public function testRejectsRemoteTarget(): void
    {
        $m = FoleysLoadHarness::create(3, 1, 2, 0);
        $m['baseUrl'] = 'https://foleys-dry-cleaning.onrender.com';
        file_put_contents($m['directory'] . '/manifest.json', json_encode($m));
        $this->expectException(RuntimeException::class);
        FoleysLoadHarness::manifest($m['directory'] . '/manifest.json');
    }

    public function testRejectsExternalDatabase(): void
    {
        $m = FoleysLoadHarness::create(3, 1, 2, 0);
        $m['database'] = FoleysLoadHarness::root() . '/tests.sqlite';
        file_put_contents($m['directory'] . '/manifest.json', json_encode($m));
        $this->expectException(RuntimeException::class);
        FoleysLoadHarness::database($m);
    }

    public function testReconciliationDetectsMissingAndDuplicateRequests(): void
    {
        [$m, $db] = $this->fixture();
        $report = FoleysLoadHarness::reconcile($m);
        $this->assertFalse($report['passed']);
        $this->assertSame([0, 1, 2], $report['missingRequestIds']);
        for ($i = 0; $i < 3; $i++) { $this->insertOrder($m, $db, $i); }
        $this->assertTrue(FoleysLoadHarness::reconcile($m)['passed']);
        $this->insertOrder($m, $db, 0);
        $report = FoleysLoadHarness::reconcile($m);
        $this->assertFalse($report['passed']);
        $this->assertContains('Duplicate request 0', $report['problems']);
    }

    public function testReconciliationDetectsWrongPricesAndIncompleteItems(): void
    {
        [$m, $db] = $this->fixture();
        for ($i = 0; $i < 3; $i++) { $this->insertOrder($m, $db, $i); }
        $db->exec('UPDATE orders SET total_cents = 1 WHERE id = 1');
        $db->exec('DELETE FROM order_items WHERE order_id = 2');
        $report = FoleysLoadHarness::reconcile($m);
        $this->assertFalse($report['passed']);
        $this->assertSame([], $report['missingRequestIds']);
        $this->assertContains('Wrong identity, status, price or timestamp for request 0', $report['problems']);
        $this->assertContains('Incomplete items for request 1', $report['problems']);
    }

    public function testIsolatedBootstrapOverridesRemoteDatabaseAndMailEnvironment(): void
    {
        $m = FoleysLoadHarness::create(1, 1, 2, 0);
        $program = 'putenv("FOLEYS_LOAD_MANIFEST=" . $argv[1]);'
            . 'putenv("DATABASE_URL=postgres://fake:fake@example.invalid/never_connect");'
            . 'putenv("RESEND_API_KEY=never-send");'
            . 'putenv("EMAIL_TRANSPORT_DEFAULT_URL=smtp://example.invalid");'
            . 'require $argv[2] . "/vendor/autoload.php";'
            . 'require $argv[2] . "/tests/load/config/bootstrap.php";'
            . '$db = \Cake\Datasource\ConnectionManager::get("default");'
            . '$safe = $db->getDriver() instanceof \Cake\Database\Driver\Sqlite'
            . ' && \Cake\Mailer\TransportFactory::get("default") instanceof \Cake\Mailer\Transport\DebugTransport;'
            . 'echo $safe ? "isolated" : "unsafe"; exit($safe ? 0 : 1);';
        $process = proc_open([PHP_BINARY, '-r', $program, $m['directory'] . '/manifest.json', FoleysLoadHarness::root()], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);
        $this->assertSame('isolated', $output);
    }

    private function fixture(): array
    {
        $m = FoleysLoadHarness::create(3, 1, 2, 0);
        $db = FoleysLoadHarness::database($m);
        $db->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, barrister_name TEXT, email TEXT, notes TEXT, status TEXT, subtotal_cents INTEGER, discount_cents INTEGER, total_cents INTEGER, submitted_at TEXT)');
        $db->exec('CREATE TABLE service_items (id INTEGER PRIMARY KEY, name TEXT, unit_price_cents INTEGER)');
        $db->exec('CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INTEGER, service_item_id INTEGER, item_name TEXT, unit_price_cents INTEGER, quantity INTEGER, line_total_cents INTEGER)');
        $db->exec("INSERT INTO service_items VALUES (1, 'Shirt', 1000), (2, 'Suit jacket', 1800), (3, 'Bar robe', 3000), (4, 'Jabot', 800)");
        return [$m, $db];
    }

    private function insertOrder(array $m, PDO $db, int $i): void
    {
        $profile = FoleysLoadHarness::profiles()[$i % 3];
        $db->prepare('INSERT INTO orders (barrister_name, email, notes, status, subtotal_cents, discount_cents, total_cents, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(['Load Test ' . $m['runId'] . ' ' . $i, $m['runId'] . '-' . $i . '@example.com', $m['runId'], 'submitted', $profile['subtotal'], $profile['discount'], $profile['total'], '2026-10-20 00:00:00']);
        $orderId = $db->lastInsertId();
        foreach ($profile['items'] as [$id, $quantity]) {
            $item = $db->query('SELECT * FROM service_items WHERE id = ' . $id)->fetch(PDO::FETCH_ASSOC);
            $db->prepare('INSERT INTO order_items (order_id, service_item_id, item_name, unit_price_cents, quantity, line_total_cents) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$orderId, $id, $item['name'], $item['unit_price_cents'], $quantity, $quantity * $item['unit_price_cents']]);
        }
    }
}
