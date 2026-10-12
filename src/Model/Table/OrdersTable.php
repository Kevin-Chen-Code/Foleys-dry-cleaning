<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Event\EventInterface;
use Cake\Validation\Validator;

final class OrdersTable extends Table
{
    public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        if ($data->offsetExists('email') && is_string($data['email'])) {
            // Trim surrounding spaces only; retain control characters so validation rejects them.
            $data['email'] = trim($data['email'], ' ');
        }
    }

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
            ->scalar('email', 'Please enter your email address to proceed')
            ->maxLength('email', 254, 'Please enter your email address to proceed')
            ->add('email', 'noControlCharacters', [
                'rule' => static fn ($value): bool => is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value) === 0,
                'message' => 'Please enter your email address to proceed',
            ])
            ->email('email', false, 'Please enter your email address to proceed')
            ->scalar('notes')->maxLength('notes', 2000)->allowEmptyString('notes');
    }
}
