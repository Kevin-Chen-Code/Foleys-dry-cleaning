<?php
declare(strict_types=1);

final class AddEmailSettings extends \Migrations\BaseMigration
{
    public function change(): void
    {
        $this->table('email_settings')
            ->addColumn('mailbox', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('reporting_day', 'string', ['limit' => 12, 'default' => 'monday'])
            ->addTimestamps()
            ->create();

        $this->table('email_settings')->insert([
            'mailbox' => null,
            'reporting_day' => 'monday',
        ])->save();
    }
}
