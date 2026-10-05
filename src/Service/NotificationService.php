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
        $barristerEmail = trim((string)($order->email ?? ''));

        $itemLines = array_map(
            fn(array $line): string => sprintf('- %s: %d x $%0.2f = $%0.2f', $line['item_name'], $line['quantity'], $line['unit_price_cents'] / 100, $line['line_total_cents'] / 100),
            $lines,
        );
        $items = implode("\n", $itemLines);
        $teamBody = "Dear team,\n\n"
            . sprintf("A barrister (%s) has submitted a request for dry cleaning:\n", $barristerEmail)
            . $items
            . sprintf("\n\nFinal price: $%0.2f\n\nKind regards,\nFoley's List dry cleaning portal", $order->total_cents / 100);
        $barristerBody = "Dear barrister,\n\n"
            . "Thank you for submitting your dry-cleaning request. We have received the following item(s):\n"
            . $items
            . sprintf("\n\nFinal price: $%0.2f\n\nKind regards,\nFoley's List dry cleaning portal", $order->total_cents / 100);

        try {
            if ($mailbox !== '') {
                (new Mailer('default'))
                    ->setTo($mailbox)
                    ->setSubject('New dry cleaning request')
                    ->deliver($teamBody);
            }
            if ($barristerEmail !== '' && $barristerEmail !== $mailbox) {
                (new Mailer('default'))
                    ->setTo($barristerEmail)
                    ->setSubject('Your dry cleaning request')
                    ->deliver($barristerBody);
            }
            return null;
        } catch (Throwable $exception) {
            Log::error('Dry-cleaning request email could not be sent: ' . $exception->getMessage());
            return 'Your request was saved, but its notification email could not be delivered. Check the outgoing email configuration.';
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
