<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

final class EmailSettingsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('email_settings');
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->email('mailbox', false, 'Enter a valid mailbox address.')->allowEmptyString('mailbox')
            ->inList('reporting_day', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
    }
}
