<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class RepairOrderTimestamps extends BaseMigration
{
    public function up(): void
    {
        // This repair is only needed for SQLite's quoted timestamp defaults.
        if ($this->getAdapter()->getAdapterType() !== 'sqlite') {
            return;
        }
        $this->execute("UPDATE orders SET created = datetime('now') WHERE created = 'CURRENT_TIMESTAMP'");
    }
}
