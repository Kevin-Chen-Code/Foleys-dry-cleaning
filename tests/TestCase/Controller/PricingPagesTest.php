<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\DiscountRulesTable;
use App\Model\Table\OrderItemsTable;
use Cake\Core\Configure;
use App\Model\Table\ServiceItemsTable;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\ORM\Locator\TableLocator;
use Cake\ORM\Table;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Closure;

final class PricingPagesTest extends TestCase
{
    use IntegrationTestTrait;

    private Connection $connection;
    private TableLocator $locator;
    private Closure $injectTables;
    private mixed $originalDiscountsEnabled;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalDiscountsEnabled = Configure::read('Foleys.discountsEnabled');
        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection->execute('PRAGMA foreign_keys = ON');
        $this->connection->execute('CREATE TABLE service_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT, unit_price_cents INTEGER, active BOOLEAN, position INTEGER, price_cap_eligible BOOLEAN DEFAULT 1, archived_at DATETIME, created DATETIME, updated DATETIME)');
        $this->connection->execute('CREATE TABLE discount_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, qualifying_item_name TEXT, minimum_item_count INTEGER, discount_type TEXT, discount_value INTEGER, active BOOLEAN, created DATETIME, updated DATETIME)');
        $this->connection->execute('CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, service_item_id INTEGER NOT NULL REFERENCES service_items(id), item_name TEXT NOT NULL, unit_price_cents INTEGER NOT NULL, quantity INTEGER NOT NULL, line_total_cents INTEGER NOT NULL, created DATETIME)');
        $this->locator = new TableLocator();
        $this->locator->set('ServiceItems', new ServiceItemsTable(['connection' => $this->connection]));
        $this->locator->set('DiscountRules', new DiscountRulesTable(['connection' => $this->connection]));
        $this->locator->set('OrderItems', new OrderItemsTable(['connection' => $this->connection]));
        $this->injectTables = function (EventInterface $event): void {
            $event->getSubject()->setTableLocator($this->locator);
        };
        EventManager::instance()->on('Controller.initialize', ['priority' => 1], $this->injectTables);
    }

    public function tearDown(): void
    {
        EventManager::instance()->off('Controller.initialize', $this->injectTables);
        Configure::write('Foleys.discountsEnabled', $this->originalDiscountsEnabled);
        $this->connection->getDriver()->disconnect();
        parent::tearDown();
    }

    public function testRequestDropdownIncludesAllNineGarmentsIncludingInactiveShirt(): void
    {
        $items = $this->locator->get('ServiceItems');
        foreach (['Shirt', 'Blazer/Bar Jacket', 'Pencil Skirt', 'Tie', 'Jabot', 'Pant', 'Blouse', 'Vest', 'Bar Robe'] as $index => $name) {
            $items->saveOrFail($items->newEntity(['name' => $name, 'unit_price_cents' => 1000, 'active' => $name !== 'Shirt', 'position' => $index]));
        }
        $this->get('/request');
        $this->assertResponseOk();
        $html = (string)$this->_response->getBody();
        preg_match('/data-options="([^"]+)"/', $html, $matches);
        $options = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(9, $options);
        $this->assertContains('Shirt', array_column($options, 'name'));
        $this->assertFalse($options[4]['price_cap_eligible']);
        $this->assertFalse($options[8]['price_cap_eligible']);
        $this->assertResponseContains('/js/order-pricing.js');
    }

    public function testFixedDiscountEditDisplaysDollarsAndSavesCents(): void
    {
        $rules = $this->locator->get('DiscountRules');
        $rule = $rules->saveOrFail($rules->newEntity(['name' => 'Collection discount', 'minimum_item_count' => 3, 'discount_type' => 'fixed', 'discount_value' => 1000, 'active' => true]));
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->get('/admin/manage-pricing');
        $this->assertResponseOk();
        $this->assertResponseContains('Fixed dollar amount');
        $this->assertResponseNotContains('Fixed cents');
        $this->assertResponseContains('value="10.00"');
        $this->enableCsrfToken();
        $this->post('/admin/manage-pricing', ['action' => 'save_rule', 'id' => $rule->id, 'name' => 'Collection discount', 'minimum_item_count' => '3', 'discount_type' => 'fixed', 'discount_value' => '10.25']);
        $this->assertRedirect('/admin/manage-pricing');
        $this->assertSame(1025, $rules->get($rule->id)->discount_value);
    }

    public function testGarmentFormsOmitPositionAndSavingKeepsDisplayOrder(): void
    {
        $items = $this->locator->get('ServiceItems');
        $item = $items->saveOrFail($items->newEntity(['name' => 'Blouse', 'unit_price_cents' => 950, 'active' => true, 'position' => 70, 'price_cap_eligible' => true]));
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->get('/admin/manage-pricing');
        $this->assertResponseOk();
        $this->assertResponseNotContains('name="position"');
        $this->assertResponseContains('for="item-eligible-' . $item->id . '"');
        $this->enableCsrfToken();
        $this->post('/admin/manage-pricing', ['action' => 'save_item', 'id' => $item->id, 'name' => 'Blouse', 'price' => '10.00', 'active' => '1', 'price_cap_eligible' => '1']);
        $this->assertRedirect('/admin/manage-pricing');
        $this->assertSame(70, $items->get($item->id)->position);
        $this->assertSame(1000, $items->get($item->id)->unit_price_cents);
        $this->post('/admin/manage-pricing', ['action' => 'save_item', 'name' => 'New garment', 'price' => '15.00', 'active' => '1', 'price_cap_eligible' => '1']);
        $this->assertRedirect('/admin/manage-pricing');
        $this->assertSame(71, $items->find()->where(['name' => 'New garment'])->firstOrFail()->position);
    }

    public function testPriceCapCanBeCreatedAndInvalidAmountsCannotBeSaved(): void
    {
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->enableCsrfToken();
        $this->post('/admin/manage-pricing', ['action' => 'save_rule', 'name' => '3+ eligible cap', 'minimum_item_count' => '3', 'discount_type' => 'price_cap', 'discount_value' => '30.50']);
        $this->assertRedirect('/admin/manage-pricing');
        $rules = $this->locator->get('DiscountRules');
        $rule = $rules->find()->firstOrFail();
        $this->assertSame(3050, $rule->discount_value);
        $this->assertSame('price_cap', $rule->discount_type);
        $this->post('/admin/manage-pricing', ['action' => 'save_rule', 'name' => 'Invalid cap', 'minimum_item_count' => '0', 'discount_type' => 'price_cap', 'discount_value' => '10.255']);
        $this->assertSame(1, $rules->find()->count());
    }

    public function testDeletingReferencedGarmentArchivesItAndPreservesRequestHistory(): void
    {
        $items = $this->locator->get('ServiceItems');
        $item = $items->saveOrFail($items->newEntity(['name' => 'Historical-only garment', 'unit_price_cents' => 950, 'active' => true]));
        $items->saveOrFail($items->newEntity(['name' => 'Inactive retained garment', 'unit_price_cents' => 500, 'active' => false]));
        $this->assertSame(1, (int)$this->connection->execute('PRAGMA foreign_keys')->fetch('assoc')['foreign_keys']);
        $this->connection->insert('order_items', [
            'order_id' => 101, 'service_item_id' => $item->id, 'item_name' => 'Original garment name',
            'unit_price_cents' => 900, 'quantity' => 2, 'line_total_cents' => 1800,
        ]);
        $before = $this->connection->execute('SELECT * FROM order_items')->fetch('assoc');

        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->enableCsrfToken();
        $this->post('/admin/manage-pricing', ['action' => 'delete_item', 'id' => $item->id]);

        $this->assertRedirect('/admin/manage-pricing');
        $this->assertFlashMessage('Garment removed from the catalogue. Existing requests have been kept.');
        $archived = $items->get($item->id);
        $this->assertNotNull($archived->archived_at);
        $this->assertFalse($archived->active);
        $this->assertSame($before, $this->connection->execute('SELECT * FROM order_items')->fetch('assoc'));
        $this->assertSame([], $this->connection->execute('PRAGMA foreign_key_check')->fetchAll('assoc'));

        foreach (['/admin/manage-pricing', '/request', '/prices'] as $path) {
            $this->get($path);
            $this->assertResponseOk();
            $this->assertResponseNotContains('Historical-only garment');
            $this->assertResponseContains('Inactive retained garment');
        }
    }

    public function testDeletingUnreferencedGarmentAlsoArchivesInsteadOfHardDeleting(): void
    {
        $items = $this->locator->get('ServiceItems');
        $item = $items->saveOrFail($items->newEntity(['name' => 'Unused garment', 'unit_price_cents' => 900, 'active' => true]));
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->enableCsrfToken();
        $this->post('/admin/manage-pricing', ['action' => 'delete_item', 'id' => $item->id]);

        $this->assertRedirect('/admin/manage-pricing');
        $this->assertNotNull($items->get($item->id)->archived_at);
        $this->assertFalse($items->get($item->id)->active);
        $this->assertSame(1, $items->find()->count());
        $this->assertSame(0, $items->find('catalogue')->count());
    }

    public function testStaleSubmissionCannotOrderAnArchivedGarment(): void
    {
        $items = $this->locator->get('ServiceItems');
        $item = $items->saveOrFail($items->newEntity(['name' => 'Archived garment', 'unit_price_cents' => 900, 'active' => true]));
        $this->assertTrue($items->archive($item));
        // A regression must never save a request or proceed to the email-delivery step.
        $this->connection->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, barrister_name TEXT, email TEXT, notes TEXT, status TEXT, subtotal_cents INTEGER, discount_cents INTEGER, total_cents INTEGER, submitted_at DATETIME, created DATETIME)');
        $orders = $this->getMockBuilder(Table::class)
            ->setConstructorArgs([['table' => 'orders', 'connection' => $this->connection]])
            ->onlyMethods(['save'])->getMock();
        $orders->expects($this->never())->method('save');
        $this->locator->set('Orders', $orders);

        $this->enableCsrfToken();
        $this->post('/request', [
            'barrister_name' => 'Test Barrister', 'email' => 'barrister@example.com',
            'items' => [['service_item_id' => $item->id, 'quantity' => 5]],
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Add at least one item to your request.');
        $this->assertSessionNotHasKey('Foleys.lastOrder');
        $this->assertSame(0, $this->connection->execute('SELECT COUNT(*) AS total FROM orders')->fetch('assoc')['total']);
    }

    public function testPausedDiscountsKeepRulesButReturnUndiscountedQuoteAndPreview(): void
    {
        Configure::write('Foleys.discountsEnabled', false);
        $items = $this->locator->get('ServiceItems');
        $item = $items->saveOrFail($items->newEntity(['name' => 'Shirt', 'unit_price_cents' => 900, 'active' => true]));
        $rules = $this->locator->get('DiscountRules');
        foreach ([
            ['name' => 'Percentage offer', 'minimum_item_count' => 3, 'discount_type' => 'percentage', 'discount_value' => 30],
            ['name' => 'Price cap offer', 'minimum_item_count' => 3, 'discount_type' => 'price_cap', 'discount_value' => 3200],
            ['name' => 'Five-item fixed offer', 'minimum_item_count' => 5, 'discount_type' => 'fixed', 'discount_value' => 1500],
        ] as $rule) {
            $rules->saveOrFail($rules->newEntity($rule + ['active' => true]));
        }
        $this->enableCsrfToken();
        $this->post('/prices', ['items' => [['service_item_id' => $item->id, 'quantity' => 6]]]);

        $this->assertResponseOk();
        $pricing = $this->viewVariable('pricing');
        $this->assertSame(5400, $pricing['subtotal']);
        $this->assertSame(5400, $pricing['total']);
        $this->assertSame(0, $pricing['discount']);
        $this->assertSame([], $pricing['appliedDiscounts']);
        $this->assertSame(3, $rules->find()->where(['active' => true])->count());

        $this->get('/request');
        $this->assertResponseOk();
        $this->assertResponseContains('data-discounts-enabled="false"');
        $this->assertResponseContains('data-discount-rules="[]"');
        $this->assertSame([], $this->viewVariable('rules'));
    }

    public function testMissingArchiveMigrationLeavesPagesUsableAndDeletionFailsSafely(): void
    {
        $this->connection->execute('ALTER TABLE service_items DROP COLUMN archived_at');
        $items = $this->locator->get('ServiceItems');
        $item = $items->saveOrFail($items->newEntity(['name' => 'Migration pending garment', 'unit_price_cents' => 900, 'active' => true]));
        $this->session(['Foleys' => ['admin' => 'admin']]);
        $this->get('/admin/manage-pricing');
        $this->assertResponseOk();
        $this->assertResponseContains('Migration pending garment');
        $this->get('/request');
        $this->assertResponseOk();
        $this->assertResponseContains('Migration pending garment');

        $this->enableCsrfToken();
        $this->post('/admin/manage-pricing', ['action' => 'delete_item', 'id' => $item->id]);
        $this->assertRedirect('/admin/manage-pricing');
        $this->assertFlashMessageContains('Apply the catalogue archive migration');
        $this->assertSame(1, $items->find()->count());
        $this->assertTrue($items->get($item->id)->active);
    }
}
