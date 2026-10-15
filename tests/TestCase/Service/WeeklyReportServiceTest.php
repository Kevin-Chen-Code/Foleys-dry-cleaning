<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\WeeklyReportService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Locator\TableLocator;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;

final class WeeklyReportServiceTest extends TestCase
{
    public function testCsvKeepsNamesAndEmailsSeparateAndLeavesLegacyNamesBlank(): void
    {
        $rows = $this->csvRows([
            ['barrister_name' => "Alex O'Connor", 'email' => 'alex@example.com'],
            ['barrister_name' => null, 'email' => 'legacy@example.com'],
        ]);

        $this->assertSame(['Barrister name', 'Email address', 'Submitted', 'Item type', 'Quantity', 'Cost', 'Discounted cost'], $rows[0]);
        $byEmail = array_column(array_slice($rows, 1), null, 1);
        $this->assertSame(["Alex O'Connor", 'alex@example.com', '2026-10-16', 'Shirt', '2', '20.00', '18.00'], $byEmail['alex@example.com']);
        $this->assertSame('', $byEmail['legacy@example.com'][0]);
    }

    public function testCsvNamesCannotBeInterpretedAsSpreadsheetFormulas(): void
    {
        $rows = $this->csvRows([
            ['barrister_name' => '=1+1', 'email' => 'alex@example.com'],
        ]);

        $this->assertSame("'=1+1", $rows[1][0]);
    }

    public function testDiscountedLineCostsAddUpToTheFinalRequestTotal(): void
    {
        $items = [
            (object)['line_total_cents' => 500],
            (object)['line_total_cents' => 1000],
        ];

        $discountedCosts = (new WeeklyReportService())->discountedLineCosts(1350, $items);

        $this->assertSame([450, 900], $discountedCosts);
        $this->assertSame(1350, array_sum($discountedCosts));
    }

    public function testDiscountedLineCostsKeepRoundingCentsInTheFinalTotal(): void
    {
        $items = [
            (object)['line_total_cents' => 333],
            (object)['line_total_cents' => 333],
            (object)['line_total_cents' => 334],
        ];

        $discountedCosts = (new WeeklyReportService())->discountedLineCosts(900, $items);

        $this->assertSame(900, array_sum($discountedCosts));
    }

    /** @param array<int, array{barrister_name: ?string, email: string}> $orders */
    private function csvRows(array $orders): array
    {
        $connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $connection->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY, barrister_name TEXT, email TEXT, status TEXT, submitted_at DATETIME, total_cents INTEGER)');
        $connection->execute('CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INTEGER, item_name TEXT, quantity INTEGER, line_total_cents INTEGER)');
        foreach ($orders as $index => $order) {
            $connection->insert('orders', [
                'id' => $index + 1,
                'barrister_name' => $order['barrister_name'],
                'email' => $order['email'],
                'status' => 'submitted',
                'submitted_at' => '2026-10-16 10:00:00',
                'total_cents' => 1800,
            ]);
            $connection->insert('order_items', [
                'id' => $index + 1,
                'order_id' => $index + 1,
                'item_name' => 'Shirt',
                'quantity' => 2,
                'line_total_cents' => 2000,
            ]);
        }
        $originalLocator = FactoryLocator::get('Table');
        $locator = new TableLocator();
        $locator->set('Orders', new Table(['table' => 'orders', 'connection' => $connection]));
        $locator->set('OrderItems', new Table(['table' => 'order_items', 'connection' => $connection]));
        FactoryLocator::add('Table', $locator);
        try {
            $csv = (new WeeklyReportService())->csv();
        } finally {
            FactoryLocator::add('Table', $originalLocator);
            $connection->getDriver()->disconnect();
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }
}
