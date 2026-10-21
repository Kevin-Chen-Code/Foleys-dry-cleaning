<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class AddReportDownloadedAt extends BaseMigration
{
    public function change(): void
    {
        $this->table('orders')
            ->addColumn('report_downloaded_at', 'datetime', ['null' => true, 'default' => null])
            ->update();
    }
}
