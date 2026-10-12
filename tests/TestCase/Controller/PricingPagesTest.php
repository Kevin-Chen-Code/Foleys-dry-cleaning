<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Model\Table\DiscountRulesTable;
use App\Model\Table\ServiceItemsTable;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\ORM\Locator\TableLocator;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Closure;

final class PricingPagesTest extends TestCase
{
    use IntegrationTestTrait;

    private Connection $connection;
    private TableLocator $locator;
    private Closure $injectTables;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection->execute('CREATE TABLE service_items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT, unit_price_cents INTEGER, active BOOLEAN, position INTEGER, price_cap_eligible BOOLEAN DEFAULT 1, created DATETIME, updated DATETIME)');
        $this->connection->execute('CREATE TABLE discount_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, qualifying_item_name TEXT, minimum_item_count INTEGER, discount_type TEXT, discount_value INTEGER, active BOOLEAN, created DATETIME, updated DATETIME)');
        $this->locator = new TableLocator();
        $this->locator->set('ServiceItems', new ServiceItemsTable(['connection' => $this->connection]));
        $this->locator->set('DiscountRules', new DiscountRulesTable(['connection' => $this->connection]));
        $this->injectTables = function (EventInterface $event): void {
            $event->getSubject()->setTableLocator($this->locator);
        };
        EventManager::instance()->on('Controller.initialize', ['priority' => 1], $this->injectTables);
    }

    public function tearDown(): void
    {
        EventManager::instance()->off('Controller.initialize', $this->injectTables);
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
}
