<?php
declare(strict_types=1);

use Migrations\BaseMigration;

final class RestoreBarristerName extends BaseMigration
{
    public function change(): void
    {
        // Historical requests have no recorded name; new requests require one in the model.
        $this->table('orders')
            ->addColumn('barrister_name', 'string', ['limit' => 150, 'null' => true])
            ->update();
    }
}
