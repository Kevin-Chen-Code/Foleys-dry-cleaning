<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Event\EventInterface;
use App\Service\OrderPricingService;

final class ServiceItemsTable extends Table
{
    public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        if (is_string($data['name'] ?? null) && OrderPricingService::isExcludedGarment($data['name'])) {
            $data['price_cap_eligible'] = false;
        }
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('service_items');
        $this->setDisplayField('name');
    }
}
