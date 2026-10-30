<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Additive only: no garments or existing requests are deleted or archived by this migration. */
final class AddServiceItemArchive extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_items')
            ->addColumn('archived_at', 'datetime', ['null' => true, 'default' => null])
            ->update();
    }
}
