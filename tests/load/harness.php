<?php
declare(strict_types=1);

/** Standalone helpers: never load app_local.php or a production connection. */
final class FoleysLoadHarness
{
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function profiles(): array
    {
        // Independent expected answers, not calculated by the pricing code under test.
        return [
            ['items' => [[1, 2], [2, 1]], 'subtotal' => 3800, 'discount' => 1680, 'total' => 2120],
            ['items' => [[3, 1], [4, 1]], 'subtotal' => 3800, 'discount' => 380, 'total' => 3420],
            ['items' => [[1, 4], [2, 2], [3, 1]], 'subtotal' => 10600, 'discount' => 6360, 'total' => 4240],
        ];
    }

    public static function manifest(string $path): array
    {
        $path = realpath($path);
        $base = realpath(self::root() . '/tmp/load-tests');
        if (!$path || !$base || basename($path) !== 'manifest.json' || dirname(dirname($path)) !== $base) {
            throw new RuntimeException('Only generated tmp/load-tests/<run>/manifest.json files are accepted.');
        }
        $m = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $dir = dirname($path);
        if (($m['mode'] ?? '') !== 'foleys-isolated-local-v1'
            || !preg_match('/^lt[0-9]{14}[a-f0-9]{8}$/D', $m['runId'] ?? '')
            || basename($dir) !== $m['runId']
            || !preg_match('/^[a-f0-9]{64}$/D', $m['token'] ?? '')
            || ($m['directory'] ?? '') !== $dir
            || ($m['database'] ?? '') !== $dir . '/requests.sqlite'
            || !preg_match('#^http://127\.0\.0\.1:[1-9][0-9]{3,4}$#D', $m['baseUrl'] ?? '')
            || ($m['requests'] ?? 0) < 1 || $m['requests'] > 10000
            || ($m['history'] ?? -1) < 0 || $m['history'] > 100000
            || ($m['duration'] ?? 0) < 1 || $m['duration'] > 3600
            || ($m['workers'] ?? 0) < 2 || $m['workers'] > 32
            || ($m['profiles'] ?? []) !== self::profiles()
        ) {
            throw new RuntimeException('Invalid isolated load-test manifest.');
        }
        if (is_link($m['database']) || (file_exists($m['database']) && realpath($m['database']) !== $m['database'])) {
            throw new RuntimeException('Refusing a database symlink or external database path.');
        }
        return $m;
    }

    public static function database(array $m): PDO
    {
        // Only a validated local SQLite filename is ever used; DATABASE_URL is deliberately ignored.
        $m = self::manifest($m['directory'] . '/manifest.json');
        $db = new PDO('sqlite:' . $m['database'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 10000');
        return $db;
    }

    public static function create(int $count, int $duration, int $workers, int $history): array
    {
        $base = self::root() . '/tmp/load-tests';
        if (!is_dir($base) && !mkdir($base, 0700, true)) {
            throw new RuntimeException('Cannot create load-test output directory.');
        }
        $runId = 'lt' . gmdate('YmdHis') . bin2hex(random_bytes(4));
        $dir = realpath($base) . '/' . $runId;
        mkdir($dir, 0700);
        foreach (['cache', 'sessions'] as $child) {
            mkdir($dir . '/' . $child, 0700);
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!$socket) {
            throw new RuntimeException('Cannot allocate a localhost port: ' . $error);
        }
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $m = [
            'mode' => 'foleys-isolated-local-v1', 'runId' => $runId,
            'token' => bin2hex(random_bytes(32)), 'directory' => $dir,
            'database' => $dir . '/requests.sqlite', 'baseUrl' => 'http://127.0.0.1:' . $port,
            'requests' => $count, 'duration' => $duration, 'workers' => $workers, 'history' => $history,
            'profiles' => self::profiles(),
        ];
        $file = fopen($dir . '/manifest.json', 'x');
        fwrite($file, json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        fclose($file);
        chmod($dir . '/manifest.json', 0600);
        file_put_contents($dir . '/k6-config.json', '{}');
        $file = fopen($m['database'], 'x');
        fclose($file);
        return self::manifest($dir . '/manifest.json');
    }

    public static function seed(array $m): void
    {
        // Migrations build the actual current app schema, solely in the new isolated database.
        if (!(new \Migrations\Migrations(['connection' => 'default']))->migrate()) {
            throw new RuntimeException('Isolated migrations failed.');
        }
        $db = self::database($m);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->beginTransaction();
        // These fixture-only tables belong to a freshly created test DB, never the user's DB.
        $db->exec('DELETE FROM discount_rules');
        $db->exec('DELETE FROM service_items');
        $item = $db->prepare('INSERT INTO service_items (id, name, unit_price_cents, active, position, price_cap_eligible, created) VALUES (?, ?, ?, 1, ?, ?, ?)');
        foreach ([[1, 'Shirt', 1000, 1], [2, 'Suit jacket', 1800, 1], [3, 'Bar robe', 3000, 0], [4, 'Jabot', 800, 0]] as [$id, $name, $price, $eligible]) {
            $item->execute([$id, $name, $price, $id, $eligible, gmdate('Y-m-d H:i:s')]);
        }
        $rule = $db->prepare('INSERT INTO discount_rules (name, minimum_item_count, discount_type, discount_value, active, created) VALUES (?, ?, ?, ?, 1, ?)');
        foreach ([['Test cap', 3, 'price_cap', 2500], ['Test percentage', 2, 'percentage', 10], ['Test fixed', 5, 'fixed', 200]] as $row) {
            $rule->execute([...$row, gmdate('Y-m-d H:i:s')]);
        }
        $db->exec("UPDATE email_settings SET mailbox = 'loadtest-team@example.com'");
        $order = $db->prepare('INSERT INTO orders (barrister_name, email, notes, status, subtotal_cents, discount_cents, total_cents, submitted_at, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $line = $db->prepare('INSERT INTO order_items (order_id, service_item_id, item_name, unit_price_cents, quantity, line_total_cents, created) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $catalogue = $db->query('SELECT id, name, unit_price_cents FROM service_items')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        for ($i = 0; $i < $m['history']; $i++) {
            $profile = self::profiles()[$i % 3];
            $when = gmdate('Y-m-d H:i:s', time() - 86400 - $i);
            $order->execute(['Synthetic History', "history-$i@example.com", 'load-test-history', 'submitted', $profile['subtotal'], $profile['discount'], $profile['total'], $when, $when]);
            $id = $db->lastInsertId();
            foreach ($profile['items'] as [$itemId, $quantity]) {
                $row = $catalogue[$itemId];
                $line->execute([$id, $itemId, $row['name'], $row['unit_price_cents'], $quantity, $quantity * $row['unit_price_cents'], $when]);
            }
        }
        $db->commit();
    }

    public static function reconcile(array $m): array
    {
        $db = self::database($m);
        $statement = $db->prepare('SELECT * FROM orders WHERE notes = ? ORDER BY id');
        $statement->execute([$m['runId']]);
        $orders = $statement->fetchAll(PDO::FETCH_ASSOC);
        $catalogue = $db->query('SELECT id, name, unit_price_cents FROM service_items')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
        $lines = $db->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY service_item_id');
        $seen = []; $problems = [];
        foreach ($orders as $order) {
            $pattern = '/^' . preg_quote($m['runId'], '/') . '-([0-9]+)@example\.com$/D';
            if (!preg_match($pattern, $order['email'], $match) || (int)$match[1] >= $m['requests']) {
                $problems[] = 'Unexpected test identity for order ' . $order['id'];
                continue;
            }
            $i = (int)$match[1];
            if (isset($seen[$i])) { $problems[] = "Duplicate request $i"; }
            $seen[$i] = true;
            $profile = self::profiles()[$i % 3];
            if ($order['barrister_name'] !== 'Load Test ' . $m['runId'] . ' ' . $i || $order['status'] !== 'submitted'
                || (int)$order['subtotal_cents'] !== $profile['subtotal'] || (int)$order['discount_cents'] !== $profile['discount']
                || (int)$order['total_cents'] !== $profile['total'] || empty($order['submitted_at'])) {
                $problems[] = "Wrong identity, status, price or timestamp for request $i";
            }
            $lines->execute([$order['id']]);
            $actual = $lines->fetchAll(PDO::FETCH_ASSOC);
            if (count($actual) !== count($profile['items'])) { $problems[] = "Incomplete items for request $i"; }
            foreach ($profile['items'] as $index => [$itemId, $quantity]) {
                $row = $actual[$index] ?? [];
                $item = $catalogue[$itemId];
                if ((int)($row['service_item_id'] ?? 0) !== $itemId || (int)($row['quantity'] ?? 0) !== $quantity
                    || ($row['item_name'] ?? '') !== $item['name'] || (int)($row['unit_price_cents'] ?? 0) !== (int)$item['unit_price_cents']
                    || (int)($row['line_total_cents'] ?? 0) !== $quantity * (int)$item['unit_price_cents']) {
                    $problems[] = "Wrong garment line for request $i";
                }
            }
        }
        $missing = array_values(array_diff(range(0, $m['requests'] - 1), array_keys($seen)));
        $total = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $orphans = (int)$db->query('SELECT COUNT(*) FROM order_items i LEFT JOIN orders o ON o.id = i.order_id WHERE o.id IS NULL')->fetchColumn();
        $passed = $missing === [] && $problems === [] && $orphans === 0 && $total === $m['history'] + $m['requests'];
        return [
            'passed' => $passed, 'runId' => $m['runId'], 'database' => 'isolated local SQLite (not Neon)',
            'plannedRequests' => $m['requests'], 'savedTestOrders' => count($orders), 'uniqueSavedRequests' => count($seen),
            'missingRequestIds' => $missing, 'problems' => $problems, 'orphanItemRows' => $orphans,
            'syntheticHistory' => $m['history'], 'totalOrders' => $total,
        ];
    }
}
