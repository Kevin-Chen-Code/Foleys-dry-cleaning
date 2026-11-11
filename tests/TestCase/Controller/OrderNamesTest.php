<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\DiscountRulesTable;
use App\Model\Table\EmailSettingsTable;
use App\Model\Table\OrderItemsTable;
use App\Model\Table\OrdersTable;
use App\Model\Table\ServiceItemsTable;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\FactoryLocator;
use Cake\Datasource\Locator\LocatorInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Mailer\Mailer;
use Cake\Mailer\Transport\DebugTransport;
use Cake\Mailer\TransportFactory;
use Cake\ORM\Locator\TableLocator;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Closure;

final class OrderNamesTest extends TestCase
{
    use IntegrationTestTrait;

    private Connection $connection;
    private TableLocator $locator;
    private LocatorInterface $originalLocator;
    private Closure $injectTables;
    private array $originalMailerConfig;

    public function setUp(): void
    {
        parent::setUp();
        // All request, reporting and notification data stays in this private database.
        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->connection->execute('CREATE TABLE service_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT, unit_price_cents INTEGER, active BOOLEAN, position INTEGER, price_cap_eligible BOOLEAN, created DATETIME, updated DATETIME)');
        $this->connection->execute('CREATE TABLE discount_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, qualifying_item_name TEXT, minimum_item_count INTEGER, discount_type TEXT, discount_value INTEGER, active BOOLEAN, created DATETIME, updated DATETIME)');
        $this->connection->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, barrister_name TEXT, email TEXT, notes TEXT, status TEXT, subtotal_cents INTEGER, discount_cents INTEGER, total_cents INTEGER, submitted_at DATETIME, created DATETIME, report_downloaded_at DATETIME)');
        $this->connection->execute('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE, service_item_id INTEGER, item_name TEXT, unit_price_cents INTEGER, quantity INTEGER, line_total_cents INTEGER, discounted_total_cents INTEGER, created DATETIME)');
        $this->connection->execute('CREATE TABLE email_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox TEXT, reporting_day TEXT, created DATETIME, updated DATETIME)');
        $this->locator = new TableLocator();
        foreach ([
            'ServiceItems' => ServiceItemsTable::class,
            'DiscountRules' => DiscountRulesTable::class,
            'Orders' => OrdersTable::class,
            'OrderItems' => OrderItemsTable::class,
            'EmailSettings' => EmailSettingsTable::class,
        ] as $alias => $tableClass) {
            $this->locator->set($alias, new $tableClass(['connection' => $this->connection]));
        }
        $this->originalLocator = FactoryLocator::get('Table');
        $this->injectTables = function (EventInterface $event): void {
            $event->getSubject()->setTableLocator($this->locator);
            // Application bootstrap creates a new locator for every request.
            // Services must use the same isolated tables as the controller.
            FactoryLocator::add('Table', $this->locator);
        };
        EventManager::instance()->on('Controller.initialize', ['priority' => 1], $this->injectTables);

        $this->originalMailerConfig = Mailer::getConfig('default');
        TransportFactory::setConfig('order-names-test', ['className' => DebugTransport::class]);
        Mailer::drop('default');
        Mailer::setConfig('default', ['transport' => 'order-names-test', 'from' => 'portal@example.com']);
    }

    public function tearDown(): void
    {
        EventManager::instance()->off('Controller.initialize', $this->injectTables);
        FactoryLocator::add('Table', $this->originalLocator);
        Mailer::drop('default');
        Mailer::setConfig('default', $this->originalMailerConfig);
        TransportFactory::drop('order-names-test');
        $this->connection->getDriver()->disconnect();
        parent::tearDown();
    }

    public function testRequestDeletionsAreReflectedInBothAdminPages(): void
    {
        $submitted = \Cake\I18n\FrozenTime::now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        foreach ([1 => 'Deleted in Console', 2 => 'Deleted by Admin', 3 => 'Keep This Request'] as $id => $name) {
            $this->connection->insert('orders', [
                'id' => $id, 'barrister_name' => $name, 'status' => 'submitted',
                'submitted_at' => $submitted, 'total_cents' => 1000,
            ]);
            $this->connection->insert('order_items', [
                'order_id' => $id, 'item_name' => 'Shirt', 'quantity' => 1, 'line_total_cents' => 1000,
            ]);
        }
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->get('/admin/weekly-report');
        $this->assertResponseContains('Deleted in Console');
        $this->assertResponseContains('Deleted by Admin');
        $this->assertResponseContains('Total billed: $30.00');

        // Simulate a change made outside the app, then delete through the admin form.
        $this->connection->delete('orders', ['id' => 1]);
        $this->get('/admin/incoming-requests');
        $this->assertResponseContains('action="/admin/incoming-requests/delete"');
        $this->assertResponseContains('Confirm delete');
        $this->enableCsrfToken();
        $this->post('/admin/incoming-requests/delete', ['id' => '2']);
        $this->assertResponseCode(303);
        $this->assertRedirect('/admin/incoming-requests');
        $this->assertSame(2, $this->locator->get('OrderItems')->find()->count());
        $this->assertSame('deleted', $this->locator->get('Orders')->get(2)->status);

        foreach (['/admin/incoming-requests', '/admin/weekly-report'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertHeader('Cache-Control', 'no-store, private');
            $this->assertResponseNotContains('Deleted in Console');
            $this->assertResponseNotContains('Deleted by Admin');
            $this->assertResponseContains('Keep This Request');
        }
        $this->assertResponseContains('Total billed: $10.00');
        $this->get('/admin/incoming-requests?deleted=1');
        $this->assertResponseContains('Deleted by Admin');
        $this->assertResponseContains('Restore request');
        $this->post('/admin/incoming-requests/restore', ['id' => '2']);
        $this->assertRedirect('/admin/incoming-requests');
        $this->assertSame('submitted', $this->locator->get('Orders')->get(2)->status);
        $this->get('/admin/weekly-report');
        $this->assertResponseContains('Deleted by Admin');
        $this->assertResponseContains('Total billed: $20.00');
    }

    public function testSubmittedNameAndEmailAreStoredAndShownSeparatelyInReports(): void
    {
        $this->connection->insert('service_items', ['id' => 1, 'name' => 'Shirt', 'unit_price_cents' => 1000, 'active' => 1, 'position' => 1, 'price_cap_eligible' => 1]);
        $this->connection->insert('discount_rules', ['id' => 1, 'name' => 'Two item discount', 'minimum_item_count' => 2, 'discount_type' => 'percentage', 'discount_value' => 10, 'active' => 1]);
        $this->connection->insert('email_settings', ['mailbox' => 'team@example.com', 'reporting_day' => 'monday']);
        $name = "Renée O'Connor";
        $email = 'renee@example.com';
        $this->enableCsrfToken();
        $this->post('/request', [
            'barrister_name' => $name,
            'email' => $email,
            'items' => [['service_item_id' => '1', 'quantity' => '2']],
        ]);

        $this->assertRedirect('/request/received');
        $this->assertSession(1800, 'Foleys.lastOrder.total');
        $this->assertSession(null, 'Foleys.lastOrder.emailProblem');
        $orders = $this->locator->get('Orders');
        $this->assertSame(1, $orders->find()->count());
        $order = $orders->find()->firstOrFail();
        $this->assertSame($name, $order->barrister_name);
        $this->assertSame($email, $order->email);
        $this->assertSame('submitted', $order->status);
        $this->assertSame(2000, $order->subtotal_cents);
        $this->assertSame(200, $order->discount_cents);
        $this->assertSame(1800, $order->total_cents);
        $line = $this->locator->get('OrderItems')->find()->firstOrFail();
        $this->assertSame($order->id, $line->order_id);
        $this->assertSame('Shirt', $line->item_name);
        $this->assertSame(2, $line->quantity);
        $this->assertSame(1000, $line->unit_price_cents);
        $this->assertSame(2000, $line->line_total_cents);

        $this->session(['Foleys' => ['admin' => 'admin']]);
        foreach (['/admin/incoming-requests', '/admin/weekly-report'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseContains('<th>Barrister name</th>');
            $this->assertResponseContains('<th>Email address</th>');
            $this->assertMatchesRegularExpression(
                '/<td>' . preg_quote(h($name), '/') . '<\/td>\s*<td>' . preg_quote($email, '/') . '<\/td>/',
                (string)$this->_response->getBody(),
            );
        }
        $this->assertResponseContains('Download Excel');
        // The page wording is editable; verify the protected download form instead.
        $this->assertResponseContains('data-report-download');
        $this->enableCsrfToken();
        $this->post('/admin/weekly-report.xlsx');
        $this->assertResponseOk();
        $this->assertHeader('Content-Type', \App\Service\WeeklyReportService::EXCEL_MIME);
        $this->assertHeader('Content-Disposition', 'attachment; filename="foleys-weekly-report.xlsx"');
        $this->assertStringStartsWith('PK', (string)$this->_response->getBody());
        $this->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/weekly-report');
        $this->assertResponseOk();
        $this->assertResponseNotContains($email);
        $this->assertResponseContains('No undownloaded requests');
        $this->assertSame('submitted', $orders->get($order->id)->status);
        $this->assertNotNull($orders->get($order->id)->report_downloaded_at);
        $this->get('/admin/incoming-requests');
        $this->assertResponseContains($email);
        $this->get('/admin/weekly-report.csv');
        $this->assertRedirect('/admin/weekly-report');
    }

    public function testThankYouPageRequiresASuccessfullySavedRequest(): void
    {
        $this->get('/request/received');
        $this->assertRedirect('/request');
    }

    public function testSamExamplePersistsShirtOfferAndSeparatePieceSpecial(): void
    {
        foreach ([[1, 'Shirt', 900], [2, 'Blazer/Bar Jacket', 1700], [3, 'Pant', 1500]] as [$id, $name, $price]) {
            $this->connection->insert('service_items', ['id' => $id, 'name' => $name, 'unit_price_cents' => $price, 'active' => 1, 'position' => $id]);
        }
        foreach (\App\Command\ConfigureSpecialsCommand::definitions() as $rule) {
            $this->connection->insert('discount_rules', $rule + ['active' => 1]);
        }
        $this->enableCsrfToken();
        $this->post('/request', [
            'barrister_name' => 'Sam Test', 'email' => 'sam@example.com',
            'items' => [
                ['service_item_id' => '1', 'quantity' => '5'],
                ['service_item_id' => '2', 'quantity' => '1'],
                ['service_item_id' => '3', 'quantity' => '1'],
            ],
        ]);
        $this->assertRedirect('/request/received');
        $this->assertSession(6000, 'Foleys.lastOrder.total');
        $order = $this->locator->get('Orders')->find()->firstOrFail();
        $this->assertSession($order->id, 'Foleys.lastOrder.id');
        $lines = $this->locator->get('OrderItems')->find()->orderBy(['id' => 'ASC'])->all()->toList();
        $this->assertSame([3000, 1594, 1406], array_map(static fn ($line) => $line->discounted_total_cents, $lines));
        $this->session(['Foleys' => ['lastOrder' => ['id' => $order->id, 'total' => 6000]]]);
        $this->get('/request/received');
        $this->assertResponseContains('Your request number is #' . $order->id);
    }

    public function testFailedItemSaveRollsBackTheWholeRequest(): void
    {
        $this->connection->insert('service_items', ['id' => 1, 'name' => 'Shirt', 'unit_price_cents' => 900, 'active' => 1, 'position' => 1]);
        $this->connection->execute("CREATE TRIGGER fail_item_save BEFORE INSERT ON order_items BEGIN SELECT RAISE(ABORT, 'Simulated item save failure'); END");
        $this->enableCsrfToken();
        $this->post('/request', [
            'barrister_name' => 'Failed Test', 'email' => 'failed@example.com',
            'items' => [['service_item_id' => '1', 'quantity' => '1']],
        ]);
        $this->assertResponseCode(500);
        $this->assertSame(0, $this->locator->get('Orders')->find()->count());
        $this->assertSame(0, $this->locator->get('OrderItems')->find()->count());
        $this->assertSessionNotHasKey('Foleys.lastOrder');
    }

    public function testSubmittedRequestStoresOnlyTheHighestQualifyingTier(): void
    {
        $this->connection->insert('service_items', ['id' => 1, 'name' => 'Blouse', 'unit_price_cents' => 1000, 'active' => 1, 'position' => 1, 'price_cap_eligible' => 1]);
        foreach ([
            ['id' => 1, 'name' => 'Three-item percentage', 'minimum_item_count' => 3, 'discount_type' => 'percentage', 'discount_value' => 20],
            ['id' => 2, 'name' => 'Three-item cap', 'minimum_item_count' => 3, 'discount_type' => 'price_cap', 'discount_value' => 2000],
            ['id' => 3, 'name' => 'Five-item fixed', 'minimum_item_count' => 5, 'discount_type' => 'fixed', 'discount_value' => 1000],
        ] as $rule) {
            $this->connection->insert('discount_rules', $rule + ['active' => 1]);
        }
        $this->connection->insert('email_settings', ['mailbox' => 'team@example.com', 'reporting_day' => 'monday']);
        $this->enableCsrfToken();
        $this->post('/request', [
            'barrister_name' => 'Test Barrister', 'email' => 'barrister@example.com',
            'items' => [['service_item_id' => '1', 'quantity' => '5']],
        ]);

        $this->assertRedirect('/request/received');
        $this->assertSession(4000, 'Foleys.lastOrder.total');
        $order = $this->locator->get('Orders')->find()->firstOrFail();
        $this->assertSame(5000, $order->subtotal_cents);
        $this->assertSame(1000, $order->discount_cents);
        $this->assertSame(4000, $order->total_cents);
        $this->assertSame(3, $this->locator->get('DiscountRules')->find()->where(['active' => true])->count());
    }

    public function testNewSpecialsPersistExactPricesAndNeverDiscountBarRobes(): void
    {
        foreach ([[1, 'Shirt', 900, 1], [2, 'Coat', 4000, 1], [3, 'Bar robe', 5000, 0]] as [$id, $name, $price, $eligible]) {
            $this->connection->insert('service_items', ['id' => $id, 'name' => $name, 'unit_price_cents' => $price, 'active' => 1, 'position' => $id, 'price_cap_eligible' => $eligible]);
        }
        foreach (\App\Command\ConfigureSpecialsCommand::definitions() as $rule) {
            $this->connection->insert('discount_rules', $rule + ['active' => 1]);
        }
        $this->connection->insert('email_settings', ['mailbox' => 'team@example.com', 'reporting_day' => 'monday']);
        $this->enableCsrfToken();
        $this->post('/request', [
            'barrister_name' => 'Test Barrister', 'email' => 'barrister@example.com',
            'items' => [
                ['service_item_id' => '1', 'quantity' => '5'],
                ['service_item_id' => '2', 'quantity' => '1'],
                ['service_item_id' => '3', 'quantity' => '1'],
            ],
        ]);
        $this->assertRedirect('/request/received');
        $this->assertSession(12000, 'Foleys.lastOrder.total');
        $order = $this->locator->get('Orders')->find()->firstOrFail();
        $this->assertSame(13500, $order->subtotal_cents);
        $this->assertSame(1500, $order->discount_cents);
        $lines = $this->locator->get('OrderItems')->find()->orderBy(['id' => 'ASC'])->all()->toList();
        $this->assertSame([3000, 4000, 5000], array_map(static fn ($line) => $line->discounted_total_cents, $lines));
        $report = (new \App\Service\WeeklyReportService())->data();
        $this->assertSame([3000, 4000, 5000], $report['discountedCostsByOrder'][(int)$order->id]);
    }

    public function testExcelReportRequiresAdministratorSession(): void
    {
        $this->get('/admin/weekly-report.xlsx');
        $this->assertRedirect('/admin/login');
    }

    public function testGetDoesNotAllowStateChangingDownloads(): void
    {
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->get('/admin/weekly-report.xlsx');
        $this->assertResponseCode(405);
    }

    public function testDownloadRequiresCsrfToken(): void
    {
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->post('/admin/weekly-report.xlsx');
        $this->assertResponseCode(403);
    }

    public function testReportKeepsOldUndownloadedRequestsVisible(): void
    {
        $cutoff = \Cake\I18n\FrozenTime::now('Australia/Melbourne')->modify('-1 day')->setTime(17, 0)
            ->setTimezone(new \DateTimeZone('UTC'));
        foreach ([-1 => 'old@example.com', 1 => 'recent@example.com'] as $seconds => $email) {
            $id = $seconds === -1 ? 1 : 2;
            $this->connection->insert('orders', [
                'id' => $id, 'barrister_name' => 'Test Barrister', 'email' => $email,
                'status' => 'submitted', 'total_cents' => 1000,
                'submitted_at' => $cutoff->modify(sprintf('%+d seconds', $seconds))->format('Y-m-d H:i:s'),
            ]);
            $this->connection->insert('order_items', [
                'order_id' => $id, 'item_name' => 'Shirt', 'quantity' => 1, 'line_total_cents' => 1000,
            ]);
        }
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->get('/admin/weekly-report');
        $this->assertResponseOk();
        $this->assertResponseContains('recent@example.com');
        $this->assertResponseContains('old@example.com');
        $this->get('/admin/incoming-requests');
        $this->assertResponseOk();
        $this->assertResponseContains('recent@example.com');
        $this->assertResponseContains('old@example.com');
        $this->assertSame(2, $this->locator->get('Orders')->find()->count());
    }
}
