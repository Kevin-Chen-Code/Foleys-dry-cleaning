<?php
declare(strict_types=1);

final class RepairEmailSettingsTimestamp extends \Migrations\BaseMigration
{
    public function up(): void
    {
        $this->execute("UPDATE email_settings SET created = datetime('now') WHERE created = 'CURRENT_TIMESTAMP'");
        $this->execute("UPDATE email_settings SET updated = datetime('now') WHERE updated = 'CURRENT_TIMESTAMP'");
    }

    public function down(): void
    {
    }
}
