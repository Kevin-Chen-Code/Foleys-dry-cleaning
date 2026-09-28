<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class SeedPrototypeCatalogue extends BaseMigration
{
    public function up(): void
    {
        $this->table('discount_rules')
            ->addColumn('qualifying_item_name', 'string', ['limit' => 120, 'null' => true, 'after' => 'name'])
            ->update();

        $this->table('service_items')->insert([
            ['name' => 'Shirt', 'description' => null, 'unit_price_cents' => 850, 'active' => true, 'position' => 10, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
            ['name' => 'Suit jacket', 'description' => null, 'unit_price_cents' => 1800, 'active' => true, 'position' => 20, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
            ['name' => 'Skirt', 'description' => null, 'unit_price_cents' => 1250, 'active' => true, 'position' => 30, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
            ['name' => 'Tie', 'description' => null, 'unit_price_cents' => 600, 'active' => true, 'position' => 40, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
            ['name' => 'Scanlan skirt', 'description' => 'Note: Scanlan skirts cost more than normal skirts of dry clean.', 'unit_price_cents' => 2250, 'active' => true, 'position' => 50, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
            ['name' => 'Coat', 'description' => null, 'unit_price_cents' => 2500, 'active' => true, 'position' => 60, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
            ['name' => 'Blouse', 'description' => null, 'unit_price_cents' => 950, 'active' => true, 'position' => 70, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
        ])->saveData();

        $this->table('discount_rules')->insert([
            ['name' => '3+ shirts discount', 'qualifying_item_name' => 'Shirt', 'minimum_item_count' => 3, 'discount_type' => 'percentage', 'discount_value' => 15, 'active' => true, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
            ['name' => '5+ items discount', 'qualifying_item_name' => null, 'minimum_item_count' => 5, 'discount_type' => 'percentage', 'discount_value' => 10, 'active' => true, 'created' => date('Y-m-d H:i:s'), 'updated' => date('Y-m-d H:i:s')],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM discount_rules WHERE name IN ('3+ shirts discount', '5+ items discount')");
        $this->execute("DELETE FROM service_items WHERE name IN ('Shirt', 'Suit jacket', 'Skirt', 'Tie', 'Scanlan skirt', 'Coat', 'Blouse')");
        $this->table('discount_rules')->removeColumn('qualifying_item_name')->update();
    }
}
