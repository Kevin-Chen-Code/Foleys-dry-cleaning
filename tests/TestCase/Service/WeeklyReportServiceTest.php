<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\WeeklyReportService;
use App\Service\NotificationService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\FactoryLocator;
use Cake\ORM\Locator\TableLocator;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use Cake\Mailer\Mailer;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use ZipArchive;
use RuntimeException;

final class WeeklyReportServiceTest extends TestCase
{
    public function testDownloadResetsPersistentlyWithoutDeletingRequestsOrItems(): void
    {
        $this->withOrders([['email' => 'first@example.com']], function ($service): void {
            $this->assertCount(1, $service->data()['orders']);
            // Merely generating an attachment is read-only.
            $service->xlsx();
            $this->assertCount(1, $service->data()['orders']);
            $xml = $this->sheet($service->downloadAndReset());
            $this->assertSame('first@example.com', $this->cell($xml, 'B2'));
            $fresh = new WeeklyReportService(new DateTimeImmutable('2026-10-16 12:00:00 UTC'));
            $this->assertSame([], $fresh->data()['orders']);
            $this->assertTable($fresh->downloadAndReset(), 'A1:G2');
            $tables = FactoryLocator::get('Table');
            $order = $tables->get('Orders')->get(1);
            $this->assertSame('submitted', $order->status);
            $this->assertNotNull($order->report_downloaded_at);
            $this->assertSame(1, $tables->get('Orders')->find()->count());
            $this->assertSame(1, $tables->get('OrderItems')->find()->count());
        });
    }

    public function testFailedWorkbookDoesNotResetAnyRequests(): void
    {
        $this->withOrders([[]], function (): void {
            $service = new WeeklyReportService(new DateTimeImmutable('2026-10-16 12:00:00 UTC'), static function (): string {
                throw new RuntimeException('Simulated Excel generation failure');
            });
            try {
                $service->downloadAndReset();
                $this->fail('Generation should have failed');
            } catch (RuntimeException $exception) {
                $this->assertSame('Simulated Excel generation failure', $exception->getMessage());
            }
            $this->assertCount(1, $service->data()['orders']);
            $this->assertNull(FactoryLocator::get('Table')->get('Orders')->get(1)->report_downloaded_at);
        });
    }

    public function testNewRequestsArrivingDuringExportAreNotCleared(): void
    {
        $this->withOrders([['email' => 'first@example.com']], function (): void {
            $tables = FactoryLocator::get('Table');
            $service = new WeeklyReportService(new DateTimeImmutable('2026-10-16 12:00:00 UTC'), static function (array $report) use ($tables): string {
                // A newly committed/backdated request must not be hidden by a time watermark.
                $connection = $tables->get('Orders')->getConnection();
                $connection->insert('orders', ['id' => 2, 'email' => 'new@example.com', 'status' => 'submitted', 'submitted_at' => '2026-10-16 11:59:00', 'total_cents' => 1000]);
                $connection->insert('order_items', ['id' => 2, 'order_id' => 2, 'item_name' => 'Shirt', 'quantity' => 1, 'line_total_cents' => 1000]);
                return (new \App\Service\WeeklyReportWorkbook())->build($report);
            });
            $xml = $this->sheet($service->downloadAndReset());
            $this->assertSame('first@example.com', $this->cell($xml, 'B2'));
            $pending = $service->data()['orders'];
            $this->assertCount(1, $pending);
            $this->assertSame('new@example.com', $pending[0]->email);
            $this->assertNull($pending[0]->report_downloaded_at);
        });
    }

    public function testDatabaseFailureRollsBackTheReset(): void
    {
        $this->withOrders([[]], function ($service): void {
            $connection = FactoryLocator::get('Table')->get('Orders')->getConnection();
            $connection->execute("CREATE TRIGGER reject_report_reset BEFORE UPDATE ON orders BEGIN SELECT RAISE(ABORT, 'Simulated reset failure'); END");
            try {
                $service->downloadAndReset();
                $this->fail('The reset should have failed');
            } catch (\Cake\Database\Exception\QueryException $exception) {
                $this->assertStringContainsString('Simulated reset failure', $exception->getMessage());
            }
            $this->assertCount(1, $service->data()['orders']);
            $this->assertNull(FactoryLocator::get('Table')->get('Orders')->get(1)->report_downloaded_at);
        });
    }

    public function testExcelKeepsNamesEmailsDatesAndPricesInTypedColumns(): void
    {
        $xlsx = $this->withOrders([
            ['barrister_name' => "Alex O'Connor", 'email' => 'alex@example.com'],
            ['barrister_name' => null, 'email' => 'legacy@example.com'],
        ], fn ($service) => $service->xlsx());
        $xml = $this->sheet($xlsx);
        $this->assertSame('Barrister name', $this->cell($xml, 'A1'));
        $this->assertSame('Email address', $this->cell($xml, 'B1'));
        $this->assertSame('', $this->cell($xml, 'A2'));
        $this->assertSame('legacy@example.com', $this->cell($xml, 'B2'));
        $this->assertSame("Alex O'Connor", $this->cell($xml, 'A3'));
        $this->assertSame('alex@example.com', $this->cell($xml, 'B3'));
        $this->assertSame('Shirt', $this->cell($xml, 'D3'));
        $this->assertSame('2', $this->cell($xml, 'E3'));
        $this->assertSame('20', $this->cell($xml, 'F3'));
        $this->assertSame('18', $this->cell($xml, 'G3'));
        $this->assertSame(36.0, $xml->evaluate('sum(//s:row[number(@r) > 1]/s:c[starts-with(@r,"G")]/s:v)'));
        foreach (['C3', 'E3', 'F3', 'G3'] as $cell) {
            $this->assertSame('n', $xml->evaluate('string(//s:c[@r="' . $cell . '"]/@t)'));
        }
        $expectedDate = (new DateTimeImmutable('2026-10-16 21:00:00 UTC'))->getTimestamp() / 86400 + 25569;
        $this->assertEqualsWithDelta($expectedDate, (float)$this->cell($xml, 'C3'), 0.0000001);
        $this->assertSame(0.0, $xml->evaluate('count(//s:f)'));
        $this->assertSame('1', $xml->evaluate('string(//s:pane/@ySplit)'));
        $this->assertTable($xlsx, 'A1:G3');
    }

    public function testExcelUserTextCannotExecuteFormulasOrBreakXml(): void
    {
        $xlsx = $this->withOrders([
            ['barrister_name' => '=1+1', 'email' => 'alex@example.com'],
            ['barrister_name' => "Renée <&> O'Connor\x01", 'email' => 'renee@example.com'],
        ], fn ($service) => $service->xlsx());
        $xml = $this->sheet($xlsx);
        $this->assertSame('=1+1', $this->cell($xml, 'A3'));
        $this->assertSame('inlineStr', $xml->evaluate('string(//s:c[@r="A3"]/@t)'));
        $this->assertSame(0.0, $xml->evaluate('count(//s:c[@r="A3"]/s:f)'));
        $this->assertSame("Renée <&> O'Connor", $this->cell($xml, 'A2'));
    }

    public function testEmptyExcelContainsOnlyTheInsertableFilterableTable(): void
    {
        $xlsx = $this->withOrders([], fn ($service) => $service->xlsx());
        $xml = $this->sheet($xlsx);
        $this->assertSame('', $this->cell($xml, 'A2'));
        $this->assertSame(0.0, $xml->evaluate('count(//s:c[s:v or s:is][ancestor::s:row/@r != "1"])'));
        $this->assertTable($xlsx, 'A1:G2');
    }

    public function testTableExpandsForSixHundredRequests(): void
    {
        $orders = array_fill(0, 600, ['barrister_name' => 'Test Barrister', 'email' => 'test@example.com']);
        $xlsx = $this->withOrders($orders, fn ($service) => $service->xlsx());
        $xml = $this->sheet($xlsx);
        $this->assertTable($xlsx, 'A1:G601');
        $this->assertSame(600.0, $xml->evaluate('count(//s:row[number(@r) >= 2])'));
        $this->assertSame(10800.0, $xml->evaluate('sum(//s:row[number(@r) > 1]/s:c[starts-with(@r,"G")]/s:v)'));
        $this->assertSame('18', $this->cell($xml, 'G601'));
    }

    public function testCutoffIsStrictAndFutureAndNonSubmittedRequestsAreExcluded(): void
    {
        $report = $this->withOrders([
            ['submitted_at' => '2026-10-15 05:59:59'], // 4:59:59 pm Melbourne
            ['submitted_at' => '2026-10-15 06:00:00'], // exactly 5 pm: excluded
            ['submitted_at' => '2026-10-15 06:00:01'],
            ['submitted_at' => '2026-10-16 00:00:00'],
            ['submitted_at' => '2026-10-16 12:00:00'], // exactly report time: included
            ['submitted_at' => '2026-10-16 12:00:01'],
            ['submitted_at' => '2026-10-16 00:00:00', 'status' => 'draft'],
            ['submitted_at' => null],
        ], fn ($service) => $service->data());
        $this->assertSame([5, 4, 3], array_map(fn ($order) => $order->id, $report['orders']));
        $this->assertSame('2026-10-15 17:00:00 +11:00', $report['cutoff']->format('Y-m-d H:i:s P'));
        $this->assertSame([3, 4, 5], array_keys($report['itemsByOrder']));
    }

    public function testCalendarCutoffHandlesWinterAndBothDaylightSavingTransitions(): void
    {
        foreach ([
            ['2026-07-15 10:00:00 UTC', '2026-07-14 17:00:00 +10:00', '2026-07-14 07:00:00'],
            ['2026-10-04 00:00:00 UTC', '2026-10-03 17:00:00 +10:00', '2026-10-03 07:00:00'],
            ['2026-04-05 00:00:00 UTC', '2026-04-04 17:00:00 +11:00', '2026-04-04 06:00:00'],
            // A Melbourne Monday may still be Sunday in UTC.
            ['2026-10-18 14:00:00 UTC', '2026-10-18 17:00:00 +11:00', '2026-10-18 06:00:00'],
        ] as [$now, $expectedCutoff, $utcCutoff]) {
            $report = $this->withOrders([
                ['submitted_at' => $utcCutoff],
                ['submitted_at' => (new DateTimeImmutable($utcCutoff))->modify('+1 second')->format('Y-m-d H:i:s')],
            ], fn ($service) => $service->data(), $now);
            $this->assertSame($expectedCutoff, $report['cutoff']->format('Y-m-d H:i:s P'));
            $this->assertSame([2], array_map(fn ($order) => $order->id, $report['orders']));
        }
    }

    public function testScheduledReportAttachesExcelWithoutSendingLiveEmail(): void
    {
        $this->withOrders([], function (): void {
            $mailer = $this->getMockBuilder(Mailer::class)->setConstructorArgs([[]])->onlyMethods(['deliver'])->getMock();
            $mailer->expects($this->once())->method('deliver')->willReturnCallback(function () use ($mailer): array {
                $this->assertSame(['team@example.com'], array_keys($mailer->getTo()));
                $attachment = $mailer->getAttachments()['foleys-weekly-report.xlsx'];
                $this->assertSame(WeeklyReportService::EXCEL_MIME, $attachment['mimetype']);
                $bytes = base64_decode($attachment['data'], true);
                $this->assertIsString($bytes);
                $this->assertStringStartsWith('PK', $bytes);
                $this->assertTable($bytes, 'A1:G2');
                return [];
            });
            $this->assertTrue((new NotificationService(static fn (): Mailer => $mailer))->sendWeeklyReport());
        });
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

    /** Private in-memory database. Never uses local or Neon request data. */
    private function withOrders(array $orders, callable $callback, string $now = '2026-10-16 12:00:00 UTC'): mixed
    {
        $connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $connection->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY, barrister_name TEXT, email TEXT, status TEXT, submitted_at DATETIME, total_cents INTEGER, report_downloaded_at DATETIME)');
        $connection->execute('CREATE TABLE order_items (id INTEGER PRIMARY KEY, order_id INTEGER, item_name TEXT, quantity INTEGER, line_total_cents INTEGER)');
        $connection->execute('CREATE TABLE email_settings (id INTEGER PRIMARY KEY, mailbox TEXT, reporting_day TEXT)');
        $connection->insert('email_settings', ['id' => 1, 'mailbox' => 'team@example.com', 'reporting_day' => 'monday']);
        foreach ($orders as $index => $order) {
            $connection->insert('orders', [
                'id' => $index + 1,
                'barrister_name' => $order['barrister_name'] ?? null,
                'email' => $order['email'] ?? 'test@example.com',
                'status' => $order['status'] ?? 'submitted',
                'submitted_at' => array_key_exists('submitted_at', $order) ? $order['submitted_at'] : '2026-10-16 10:00:00',
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
        $locator->set('EmailSettings', new Table(['table' => 'email_settings', 'connection' => $connection]));
        FactoryLocator::add('Table', $locator);
        try {
            return $callback(new WeeklyReportService(new DateTimeImmutable($now)));
        } finally {
            FactoryLocator::add('Table', $originalLocator);
            $connection->getDriver()->disconnect();
        }

    }

    private function part(string $xlsx, string $name): DOMXPath
    {
        $path = tempnam(sys_get_temp_dir(), 'report-test-');
        $zip = new ZipArchive();
        try {
            file_put_contents($path, $xlsx);
            $this->assertTrue($zip->open($path));
            $document = new DOMDocument();
            $this->assertTrue($document->loadXML($zip->getFromName($name), LIBXML_NONET));
            $xml = new DOMXPath($document);
            $xml->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            return $xml;
        } finally {
            $zip->close();
            unlink($path);
        }
    }

    private function sheet(string $xlsx): DOMXPath
    {
        return $this->part($xlsx, 'xl/worksheets/sheet1.xml');
    }

    private function cell(DOMXPath $xml, string $cell): string
    {
        return $xml->evaluate('string(//s:c[@r="' . $cell . '"]/s:v | //s:c[@r="' . $cell . '"]/s:is/s:t)');
    }

    private function assertTable(string $xlsx, string $range): void
    {
        $xml = $this->part($xlsx, 'xl/tables/table1.xml');
        $this->assertSame('BarristerRequests', $xml->evaluate('string(/s:table/@name)'));
        $this->assertSame($range, $xml->evaluate('string(/s:table/@ref)'));
        $this->assertSame($range, $xml->evaluate('string(/s:table/s:autoFilter/@ref)'));
        $this->assertSame(7.0, $xml->evaluate('count(/s:table/s:tableColumns/s:tableColumn)'));
        // Every worksheet cell must be inside the single table. No title/footer/spacer rows.
        $sheet = $this->sheet($xlsx);
        $lastRow = (int)substr($range, strrpos($range, 'G') + 1);
        $this->assertSame((float)$lastRow, $sheet->evaluate('count(//s:sheetData/s:row)'));
        $this->assertSame('1', $sheet->evaluate('string(//s:sheetData/s:row[1]/@r)'));
        $this->assertSame((string)$lastRow, $sheet->evaluate('string(//s:sheetData/s:row[last()]/@r)'));
        $this->assertSame((float)($lastRow * 7), $sheet->evaluate('count(//s:sheetData/s:row/s:c)'));
        $this->assertSame(1.0, $sheet->evaluate('count(//s:tableParts/s:tablePart)'));
        $workbook = $this->part($xlsx, 'xl/workbook.xml');
        $this->assertSame(1.0, $workbook->evaluate('count(//s:sheet)'));
    }
}
