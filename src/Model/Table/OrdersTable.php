<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

final class OrdersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('orders');
        $this->hasMany('OrderItems');
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('email', 'create', 'Please enter your email address to proceed')
            ->notEmptyString('email', 'Please enter your email address to proceed')
            ->email('email', false, 'Please enter your email address to proceed')
            ->scalar('notes')->maxLength('notes', 2000)->allowEmptyString('notes');
    }
}
