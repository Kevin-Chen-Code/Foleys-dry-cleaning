<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class RepairAllTimestampLiterals extends BaseMigration
{
    public function up(): void
    {
        // PostgreSQL stores CURRENT_TIMESTAMP correctly; only SQLite needs this repair.
        if ($this->getAdapter()->getAdapterType() !== 'sqlite') {
            return;
        }
        foreach (['orders', 'order_items', 'service_items', 'discount_rules'] as $table) {
            $this->execute("UPDATE {$table} SET created = datetime('now') WHERE created = 'CURRENT_TIMESTAMP'");
            $this->execute("UPDATE {$table} SET updated = datetime('now') WHERE updated = 'CURRENT_TIMESTAMP'");
        }
    }
}
