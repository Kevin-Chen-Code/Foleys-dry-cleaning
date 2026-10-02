<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\NotificationService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Datasource\FactoryLocator;
use DateTimeImmutable;

final class SendWeeklyReportCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $settings = FactoryLocator::get('Table')->get('EmailSettings')->find()->first();
        $today = strtolower((new DateTimeImmutable('now'))->format('l'));
        if (!$settings || $settings->reporting_day !== $today) {
            $io->out('No weekly report is due today.');
            return static::CODE_SUCCESS;
        }

        if (!(new NotificationService())->sendWeeklyReport()) {
            $io->err('Weekly report was not sent. Check the configured mailbox and mail transport.');
            return static::CODE_ERROR;
        }

        $io->success('Weekly report sent.');
        return static::CODE_SUCCESS;
    }
}
