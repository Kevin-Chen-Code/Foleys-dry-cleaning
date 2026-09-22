<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class CreateDryCleaningPortal extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_items')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('unit_price_cents', 'integer')
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('position', 'integer', ['default' => 0])
            ->addTimestamps()
            ->addIndex(['active', 'position'])
            ->create();

        $this->table('discount_rules')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('minimum_item_count', 'integer')
            ->addColumn('discount_type', 'string', ['limit' => 20])
            ->addColumn('discount_value', 'integer')
            ->addColumn('active', 'boolean', ['default' => true])
            ->addTimestamps()
            ->create();

        $this->table('orders')
            ->addColumn('barrister_name', 'string', ['limit' => 150])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 30, 'default' => 'submitted'])
            ->addColumn('subtotal_cents', 'integer')
            ->addColumn('discount_cents', 'integer', ['default' => 0])
            ->addColumn('total_cents', 'integer')
            ->addColumn('submitted_at', 'datetime')
            ->addTimestamps()
            ->addIndex(['status', 'submitted_at'])
            ->create();

        $this->table('order_items')
            ->addColumn('order_id', 'integer')
            ->addColumn('service_item_id', 'integer')
            ->addColumn('item_name', 'string', ['limit' => 120])
            ->addColumn('unit_price_cents', 'integer')
            ->addColumn('quantity', 'integer')
            ->addColumn('line_total_cents', 'integer')
            ->addTimestamps()
            ->addIndex(['order_id'])
            ->addForeignKey('order_id', 'orders', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('service_item_id', 'service_items', 'id')
            ->create();
    }
}
