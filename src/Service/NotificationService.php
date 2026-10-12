<?php
declare(strict_types=1);

namespace App\Service;

use App\Mailer\Transport\ResendTransport;
use Cake\Datasource\FactoryLocator;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Closure;
use Throwable;

final class NotificationService
{
    public function __construct(private ?Closure $mailerFactory = null)
    {
    }

    /** Configuration checks only; provider delivery is confirmed separately. */
    public function deliveryStatus(): array
    {
        $issues = [];
        try {
            $transport = TransportFactory::get('default');
            if (!$transport instanceof ResendTransport) {
                $issues[] = 'The app is not using Resend. Configure RESEND_API_KEY and EMAIL_FROM for HTTPS delivery.';
            } elseif (trim((string)$transport->getConfig('apiKey')) === '') {
                $issues[] = 'RESEND_API_KEY is missing. Add the API key in the environment of the server running this app.';
            }
            if (empty(Mailer::getConfig('default')['from'])) {
                $issues[] = 'EMAIL_FROM is missing. Set it to an address on a sending domain verified in Resend.';
            } else {
                $from = (new Mailer('default'))->getFrom();
                $address = (string)array_key_first($from);
                if ($address === '' || str_ends_with($address, '@localhost')) {
                    $issues[] = 'EMAIL_FROM must be an address on a sending domain verified in Resend.';
                }
            }
        } catch (Throwable $exception) {
            $issues[] = 'The sender or transport configuration is invalid. Set EMAIL_FROM to a valid address (optionally Name <address>).';
        }

        return ['ready' => $issues === [], 'issues' => $issues];
    }

    private function mailer(): Mailer
    {
        return $this->mailerFactory !== null ? ($this->mailerFactory)() : new Mailer('default');
    }

    /**
     * Attempts each recipient independently; a saved request is never rolled back by email failure.
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

        $failed = $mailbox === '';
        if ($mailbox === '') {
            Log::warning('Dry-cleaning team notification skipped: no mailbox is saved in Email settings.');
        }
        $messages = [];
        if ($mailbox !== '') {
            $messages[] = ['recipient' => $mailbox, 'subject' => 'New dry cleaning request', 'body' => $teamBody, 'label' => 'team'];
        }
        if ($barristerEmail !== '') {
            // Deliver both distinct greetings even if the nominated mailbox matches the barrister.
            $messages[] = ['recipient' => $barristerEmail, 'subject' => 'Your dry cleaning request', 'body' => $barristerBody, 'label' => 'barrister'];
        } else {
            $failed = true;
        }
        foreach ($messages as $message) {
            try {
                $result = $this->mailer()
                    ->setEmailFormat('text')
                    ->setTo($message['recipient'])
                    ->setSubject($message['subject'])
                    ->deliver($message['body']);
                Log::info(sprintf('Dry-cleaning %s notification accepted for request %s; provider email ID: %s', $message['label'], $order->id ?? 'unknown', $result['id'] ?? 'unavailable'));
            } catch (Throwable $exception) {
                $failed = true;
                Log::error(sprintf('Dry-cleaning %s notification failed for request %s: %s', $message['label'], $order->id ?? 'unknown', $exception->getMessage()));
            }
        }

        return $failed ? 'Your request was saved, but one or more confirmation emails could not be sent. The administrator can check Email settings and the delivery logs.' : null;
    }

    /** Sends only when the administrator explicitly presses the test button. */
    public function sendTestEmail(): bool
    {
        $settings = FactoryLocator::get('Table')->get('EmailSettings')->find()->first();
        if (empty($settings?->mailbox)) {
            return false;
        }
        try {
            $result = $this->mailer()
                ->setEmailFormat('text')
                ->setTo($settings->mailbox)
                ->setSubject('Foley\'s List email delivery test')
                ->deliver("Dear team,\n\nThis is a delivery test from Foley's List dry cleaning portal.\n\nKind regards,\nFoley's List");
            Log::info('Dry-cleaning email test accepted; provider email ID: ' . ($result['id'] ?? 'unavailable'));
            return true;
        } catch (Throwable $exception) {
            Log::error('Dry-cleaning email test failed: ' . $exception->getMessage());
            return false;
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
            $this->mailer()
                ->setEmailFormat('text')
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
