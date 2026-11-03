<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query\SelectQuery;
use Cake\Database\Exception\QueryException;
use App\Service\OrderPricingService;

final class ServiceItemsTable extends Table
{
    private bool $archiveSchemaChecked = false;

    /** Refresh a stale negative cache once per table instance, or explicitly before removal. */
    private function supportsArchive(bool $forceRefresh = false): bool
    {
        if ($forceRefresh || (!$this->getSchema()->hasColumn('archived_at') && !$this->archiveSchemaChecked)) {
            $this->setSchema($this->getConnection()->getSchemaCollection()->describe(
                $this->getTable(), ['forceRefresh' => true],
            ));
            $this->archiveSchemaChecked = true;
        }
        return $this->getSchema()->hasColumn('archived_at');
    }

    /** Active is independent of deletion: inactive, unarchived garments still belong in the catalogue. */
    public function findCatalogue(SelectQuery $query): SelectQuery
    {
        // Keep pages usable during deployment until the administrator applies the migration.
        if ($this->supportsArchive()) {
            $query->where([$this->aliasField('archived_at') . ' IS' => null]);
        }
        return $query;
    }

    /** Remove from the catalogue without breaking historical order-item foreign keys. */
    public function archive(EntityInterface $entity): bool
    {
        if (!$this->supportsArchive()) {
            return false;
        }
        $entity->set('archived_at', FrozenTime::now());
        $entity->set('active', false);
        return (bool)$this->save($entity);
    }

    /** Preserve referenced garments; unused rows can be removed even before the archive migration. */
    public function removeFromCatalogue(EntityInterface $entity): bool
    {
        if ($this->supportsArchive(true)) {
            return $this->archive($entity);
        }

        // Use this table's connection, including in isolated tests. Never change historical rows.
        $referenced = $this->getConnection()->selectQuery()
            ->select(['service_item_id'])->from('order_items')
            ->where(['service_item_id' => (int)$entity->get('id')])->limit(1)
            ->execute()->fetch('assoc');
        if ($referenced !== false) {
            return false;
        }

        try {
            // Cake wraps this in a transaction. The FK also protects a concurrent new request.
            return $this->delete($entity);
        } catch (QueryException $exception) {
            $previous = $exception->getPrevious();
            $state = $previous instanceof \PDOException
                ? (string)($previous->errorInfo[0] ?? $previous->getCode()) : '';
            $foreignKeyFailure = $state === '23503'
                || ($state === '23000' && stripos($exception->getMessage(), 'foreign key') !== false);
            if (!$foreignKeyFailure) {
                throw $exception;
            }
            return false;
        }
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
