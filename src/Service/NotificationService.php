<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\FactoryLocator;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Throwable;

final class NotificationService
{
    /**
     * Returns an explanation only when a configured email transport fails.
     */
    public function sendRequestSubmitted(object $order, array $lines): ?string
    {
        $settings = FactoryLocator::get('Table')->get('EmailSettings')->find()->first();
        $mailbox = trim((string)($settings?->mailbox ?? ''));
        if ($mailbox === '') {
            return null;
        }

        $itemLines = array_map(
            fn(array $line): string => sprintf('- %s: %d x $%0.2f = $%0.2f', $line['item_name'], $line['quantity'], $line['unit_price_cents'] / 100, $line['line_total_cents'] / 100),
            $lines,
        );
        $body = "Dear team,\n\n"
            . sprintf("Barrister %s has submitted a request for dry cleaning, consisting of the following item(s):\n", $order->barrister_name)
            . implode("\n", $itemLines)
            . sprintf("\n\nFinal price: $%0.2f\n\nKind regards,\nDry cleaning system", $order->total_cents / 100);

        try {
            (new Mailer('default'))
                ->setTo($mailbox)
                ->setSubject('New dry cleaning request')
                ->deliver($body);
            return null;
        } catch (Throwable $exception) {
            Log::error('Dry-cleaning request email could not be sent: ' . $exception->getMessage());
            return 'Your request was saved, but its notification email could not be delivered. Check the SMTP settings.';
        }
    }

    public function sendWeeklyReport(): bool
    {
        $settings = FactoryLocator::get('Table')->get('EmailSettings')->find()->first();
        $mailbox = trim((string)($settings?->mailbox ?? ''));
        if ($mailbox === '') {
            return false;
        }

        $csv = (new WeeklyReportService())->csv();
        try {
            (new Mailer('default'))
                ->setTo($mailbox)
                ->setSubject('Weekly barrister dry cleaning report')
                ->setAttachments(['foleys-weekly-report.csv' => ['data' => $csv, 'mimetype' => 'text/csv']])
                ->deliver("Dear team,\n\nPlease see the attached weekly report for barrister dry cleaning requests.\n\nKind regards,\nDry cleaning system");
            return true;
        } catch (Throwable $exception) {
            Log::error('Weekly dry-cleaning report email could not be sent: ' . $exception->getMessage());
            return false;
        }
    }
}
