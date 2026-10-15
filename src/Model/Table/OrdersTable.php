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
        if (is_string($data['barrister_name'] ?? null)) {
            $data['barrister_name'] = trim(preg_replace('/\p{Zs}+/u', ' ', $data['barrister_name']) ?? $data['barrister_name'], ' ');
        }
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
            ->requirePresence('barrister_name', 'create', 'Please enter your name to proceed')
            ->notEmptyString('barrister_name', 'Please enter your name to proceed')
            ->scalar('barrister_name', 'Please enter a valid name')
            ->maxLength('barrister_name', 150, 'Please enter a name of 150 characters or fewer')
            ->add('barrister_name', 'plainTextName', [
                'rule' => static fn ($value): bool => is_string($value)
                    && preg_match('/[\x00-\x1F\x7F<>]/u', $value) === 0
                    && preg_match('/\p{L}/u', $value) === 1,
                'message' => 'Please enter a valid name',
            ])
            ->requirePresence('email', 'create', 'Please enter your email address to proceed')
            ->notEmptyString('email', 'Please enter your email address to proceed')
            ->scalar('email', 'Please a valid email address to proceed')
            ->maxLength('email', 254, 'Please a valid email address to proceed')
            ->add('email', 'noControlCharacters', [
                'rule' => static fn ($value): bool => is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value) === 0,
                'message' => 'Please a valid email address to proceed',
            ])
            ->email('email', false, 'Please a valid email address to proceed')
            ->scalar('notes')->maxLength('notes', 2000)->allowEmptyString('notes');
    }
}
