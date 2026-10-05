<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class RemoveBarristerNameFromOrders extends BaseMigration
{
    public function change(): void
    {
        $this->table('orders')
            ->removeColumn('barrister_name')
            ->update();
    }
}
