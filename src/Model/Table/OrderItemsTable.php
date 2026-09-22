<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

final class OrderItemsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('order_items');
        $this->belongsTo('Orders');
        $this->belongsTo('ServiceItems');
    }
}
