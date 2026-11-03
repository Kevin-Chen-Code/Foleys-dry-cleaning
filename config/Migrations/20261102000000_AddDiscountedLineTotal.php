<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Exact charged prices for new orders; old requests retain their original totals. */
final class AddDiscountedLineTotal extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('order_items');
        if (!$table->hasColumn('discounted_total_cents')) {
            $table->addColumn('discounted_total_cents', 'integer', ['null' => true, 'default' => null])->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('order_items');
        if ($table->hasColumn('discounted_total_cents')) {
            $table->removeColumn('discounted_total_cents')->update();
        }
    }
}
