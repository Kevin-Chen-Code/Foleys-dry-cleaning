<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query\SelectQuery;
use App\Service\OrderPricingService;

final class ServiceItemsTable extends Table
{
    /** Active is independent of deletion: inactive, unarchived garments still belong in the catalogue. */
    public function findCatalogue(SelectQuery $query): SelectQuery
    {
        // Keep pages usable during deployment until the administrator applies the migration.
        if ($this->getSchema()->hasColumn('archived_at')) {
            $query->where([$this->aliasField('archived_at') . ' IS' => null]);
        }
        return $query;
    }

    /** Remove from the catalogue without breaking historical order-item foreign keys. */
    public function archive(EntityInterface $entity): bool
    {
        if (!$this->getSchema()->hasColumn('archived_at')) {
            return false;
        }
        $entity->set('archived_at', FrozenTime::now());
        $entity->set('active', false);
        return (bool)$this->save($entity);
    }

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
