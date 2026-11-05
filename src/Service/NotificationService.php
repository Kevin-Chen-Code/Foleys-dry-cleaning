<?php
declare(strict_types=1);

namespace App\Service;

use App\Mailer\Transport\ResendTransport;
use Cake\Datasource\FactoryLocator;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Cake\View\View;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
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

    /** Sends one confirmation to the barrister, with the administrator copied. */
    public function sendRequestSubmitted(object $order, array $lines): ?string
    {
        $barristerEmail = trim((string)($order->email ?? ''));

        try {
            $firstName = preg_split('/\s+/u', trim((string)($order->barrister_name ?? '')))[0] ?: 'barrister';
            $submitted = $order->submitted_at ?? null;
            $submittedDate = $submitted instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($submitted)->setTimezone(new DateTimeZone('Australia/Melbourne'))->format('d M Y H:i')
                : '';
            $discountedCosts = (new WeeklyReportService())->discountedLineCosts(
                (int)$order->total_cents,
                array_map(static fn (array $line): object => (object)$line, $lines),
            );
            $view = new View();
            $view->set(compact('firstName', 'submittedDate', 'lines', 'discountedCosts', 'order'));
            $barristerBody = $view->render('email/html/request_submitted', false);
            $result = $this->mailer()
                ->setEmailFormat('html')
                ->setTo($barristerEmail)
                ->setCc('admin@foleys.com.au')
                ->setSubject('Your dry cleaning request')
                ->deliver($barristerBody);
            Log::info(sprintf('Dry-cleaning confirmation accepted for request %s; provider email ID: %s', $order->id ?? 'unknown', $result['id'] ?? 'unavailable'));
        } catch (Throwable $exception) {
            // Email failure must not undo a successfully saved request.
            Log::error(sprintf('Dry-cleaning confirmation failed for request %s: %s', $order->id ?? 'unknown', $exception->getMessage()));
        }

        return null;
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

        try {
            $xlsx = (new WeeklyReportService())->xlsx();
            $this->mailer()
                ->setEmailFormat('text')
                ->setTo($mailbox)
                ->setSubject('Weekly barrister dry cleaning report')
                ->setAttachments(['foleys-weekly-report.xlsx' => ['data' => $xlsx, 'mimetype' => WeeklyReportService::EXCEL_MIME]])
                ->deliver("Dear team,\n\nPlease see the attached weekly report for barrister dry cleaning requests.\n\nKind regards,\nDry cleaning system");
            return true;
        } catch (Throwable $exception) {
            Log::error('Weekly dry-cleaning report email could not be sent: ' . $exception->getMessage());
            return false;
        }
    }
}
