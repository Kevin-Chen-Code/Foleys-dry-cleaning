<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class RepairOrderTimestamps extends BaseMigration
{
    public function up(): void
    {
        $this->execute("UPDATE orders SET created = datetime('now') WHERE created = 'CURRENT_TIMESTAMP'");
    }
}
