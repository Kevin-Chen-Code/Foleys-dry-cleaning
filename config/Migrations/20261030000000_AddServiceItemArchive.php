<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Additive only: no garments or existing requests are deleted or archived by this migration. */
final class AddServiceItemArchive extends BaseMigration
{
    public function up(): void
    {
        $table = $this->table('service_items');
        if (!$table->hasColumn('archived_at')) {
            $table->addColumn('archived_at', 'datetime', ['null' => true, 'default' => null])->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('service_items');
        if ($table->hasColumn('archived_at')) {
            $table->removeColumn('archived_at')->update();
        }
    }
}
