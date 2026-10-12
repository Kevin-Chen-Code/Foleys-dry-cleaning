<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

final class DiscountRulesTable extends Table
{
    /** Forms accept dollars; stored monetary values remain integer cents. */
    public static function formValueToStorage(mixed $value, string $type): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string)$value);
        if ($type === 'percentage') {
            if (!preg_match('/^\d+$/D', $value) || strlen(ltrim($value, '0')) > 3) {
                return null;
            }
            return (int)$value <= 100 ? (int)$value : null;
        }
        if (!in_array($type, ['fixed', 'price_cap'], true) || !preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $value, $matches)) {
            return null;
        }
        $dollars = ltrim($matches[1], '0');
        if (strlen($dollars) > 8) {
            return null;
        }
        $cents = (int)$dollars * 100 + (int)str_pad($matches[2] ?? '', 2, '0');
        return $cents <= 2147483647 ? $cents : null;
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('name', 'create')->notEmptyString('name')->scalar('name')->maxLength('name', 120)
            ->requirePresence('minimum_item_count', 'create')->integer('minimum_item_count')->greaterThanOrEqual('minimum_item_count', 1)
            ->requirePresence('discount_type', 'create')->inList('discount_type', ['percentage', 'fixed', 'price_cap'])
            ->requirePresence('discount_value', 'create')->notEmptyString('discount_value')
            ->nonNegativeInteger('discount_value')->lessThanOrEqual('discount_value', 2147483647)
            ->add('discount_value', 'percentageRange', [
                'rule' => static fn ($value, array $context): bool => ($context['data']['discount_type'] ?? '') !== 'percentage' || $value <= 100,
                'message' => 'Percentage must be between 0 and 100.',
            ]);
    }

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('discount_rules');
    }
}
