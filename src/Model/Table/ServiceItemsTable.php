<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

final class ServiceItemsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('service_items');
        $this->setDisplayField('name');
    }
}
