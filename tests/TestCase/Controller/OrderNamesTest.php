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
        $this->connection->execute('CREATE TABLE service_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT, unit_price_cents INTEGER, active BOOLEAN, position INTEGER, price_cap_eligible BOOLEAN, created DATETIME, updated DATETIME)');
        $this->connection->execute('CREATE TABLE discount_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, qualifying_item_name TEXT, minimum_item_count INTEGER, discount_type TEXT, discount_value INTEGER, active BOOLEAN, created DATETIME, updated DATETIME)');
        $this->connection->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, barrister_name TEXT, email TEXT, notes TEXT, status TEXT, subtotal_cents INTEGER, discount_cents INTEGER, total_cents INTEGER, submitted_at DATETIME, created DATETIME)');
        $this->connection->execute('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER, service_item_id INTEGER, item_name TEXT, unit_price_cents INTEGER, quantity INTEGER, line_total_cents INTEGER, created DATETIME)');
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
    }
}
