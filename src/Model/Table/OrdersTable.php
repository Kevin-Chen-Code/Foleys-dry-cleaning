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
            ->scalar('barrister_name')->maxLength('barrister_name', 150)->requirePresence('barrister_name', 'create')->notEmptyString('barrister_name', 'Please enter your name.')
            ->email('email', false, 'Please enter a valid email address.')->allowEmptyString('email')
            ->scalar('notes')->maxLength('notes', 2000)->allowEmptyString('notes');
    }
}
