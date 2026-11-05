<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Table\EmailSettingsTable;
use App\Service\NotificationService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\FactoryLocator;
use Cake\Mailer\Mailer;
use Cake\TestSuite\TestCase;
use RuntimeException;

final class NotificationServiceTest extends TestCase
{
    private Connection $connection;
    private EmailSettingsTable $settings;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection(['driver' => Sqlite::class, 'database' => ':memory:']);
        $this->connection->execute('CREATE TABLE email_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox TEXT, reporting_day TEXT)');
        $this->settings = new EmailSettingsTable(['table' => 'email_settings', 'connection' => $this->connection]);
        $locator = FactoryLocator::get('Table');
        $locator->remove('EmailSettings');
        $locator->set('EmailSettings', $this->settings);
    }

    public function tearDown(): void
    {
        $this->connection->getDriver()->disconnect();
        parent::tearDown();
    }

    public function testConfirmationCopiesAdminWithoutNominatedMailbox(): void
    {
        $messages = [];
        $mailer = $this->recordingMailer(1, $messages);
        $warning = (new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($this->order(), $this->lines());
        $this->assertNull($warning);
        $this->assertSame(['barrister@example.com'], $messages[0]['to']);
        $this->assertSame(['admin@foleys.com.au'], $messages[0]['cc']);
        $this->assertSame('Your dry cleaning request', $messages[0]['subject']);
        $this->assertStringContainsString('Dear Alex,', $messages[0]['body']);
        $this->assertSame('html', $messages[0]['format']);
        $this->assertStringContainsString('04/11/2026', $messages[0]['body']);
        $this->assertStringContainsString('<th scope="col">Discounted cost</th>', $messages[0]['body']);
        $this->assertStringContainsString('Suit jacket', $messages[0]['body']);
        $this->assertStringContainsString('$32.40', $messages[0]['body']);
        $this->assertStringContainsString('$5.40', $messages[0]['body']);
        $this->assertStringNotContainsString('$36.00', $messages[0]['body']);
        $this->assertStringContainsString('Total: $37.80 AUD', $messages[0]['body']);
    }

    public function testStoredMailboxDoesNotCauseDuplicateTeamMessage(): void
    {
        $this->saveMailbox('admin@foleys.com.au');
        $messages = [];
        $mailer = $this->recordingMailer(1, $messages);
        (new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($this->order(), $this->lines());
        $this->assertCount(1, $messages);
        $this->assertSame(['admin@foleys.com.au'], $messages[0]['cc']);
    }

    public function testFiveShirtsUseSavedDiscountedChargeAndEscapeUserText(): void
    {
        $messages = [];
        $mailer = $this->recordingMailer(1, $messages);
        $order = $this->order();
        $order->barrister_name = '<Alex> Example';
        $order->total_cents = 3000;
        (new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($order, [
            ['item_name' => 'Shirt <script>alert(1)</script>', 'quantity' => 5,
                'line_total_cents' => 4500, 'discounted_total_cents' => 3000],
        ]);
        $html = $messages[0]['body'];
        $this->assertStringContainsString('Dear &lt;Alex&gt;,', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('>$30.00</td>', $html);
        $this->assertStringContainsString('Total: $30.00 AUD', $html);
        $this->assertStringNotContainsString('$45.00', $html);
        $this->assertStringNotContainsString('$33.00', $html);
    }

    public function testDeliveryFailureDoesNotUndoSavedRequest(): void
    {
        $mailer = $this->getMockBuilder(Mailer::class)->setConstructorArgs([[]])->onlyMethods(['deliver'])->getMock();
        $mailer->expects($this->once())->method('deliver')->willThrowException(new RuntimeException('Simulated rejection'));
        $this->assertNull((new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($this->order(), $this->lines()));
    }

    private function recordingMailer(int $expectedMessages, array &$messages): Mailer
    {
        $mailer = $this->getMockBuilder(Mailer::class)->setConstructorArgs([[]])->onlyMethods(['deliver'])->getMock();
        $mailer->expects($this->exactly($expectedMessages))->method('deliver')->willReturnCallback(
            static function (string $body) use ($mailer, &$messages): array {
                $messages[] = [
                    'to' => array_keys($mailer->getTo()),
                    'cc' => array_keys($mailer->getCc()),
                    'subject' => $mailer->getSubject(),
                    'body' => $body,
                    'format' => $mailer->getEmailFormat(),
                ];
                return ['id' => 'test-email-id'];
            },
        );
        return $mailer;
    }

    private function saveMailbox(string $mailbox): void
    {
        $this->settings->saveOrFail($this->settings->newEntity(['mailbox' => $mailbox, 'reporting_day' => 'monday']));
    }

    private function order(): object
    {
        return (object)['barrister_name' => 'Alex Example', 'submitted_at' => new \DateTimeImmutable('2026-11-03 14:30:00 UTC'), 'id' => 17, 'email' => 'barrister@example.com', 'total_cents' => 3780];
    }

    private function lines(): array
    {
        return [
            ['item_name' => 'Suit jacket', 'quantity' => 2, 'unit_price_cents' => 1800, 'line_total_cents' => 3600],
            ['item_name' => 'Shirt', 'quantity' => 1, 'unit_price_cents' => 600, 'line_total_cents' => 600],
        ];
    }
}

