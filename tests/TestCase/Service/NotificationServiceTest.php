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

    public function testBarristerIsAttemptedWhenTeamDeliveryFails(): void
    {
        $this->saveMailbox('team@example.com');
        $messages = [];
        $mailer = $this->getMockBuilder(Mailer::class)->setConstructorArgs([[]])->onlyMethods(['deliver'])->getMock();
        $mailer->expects($this->exactly(2))->method('deliver')->willReturnCallback(
            function (string $body) use ($mailer, &$messages): array {
                $messages[] = ['to' => array_keys($mailer->getTo()), 'subject' => $mailer->getSubject(), 'body' => $body];
                if (count($messages) === 1) {
                    throw new RuntimeException('Simulated team transport rejection');
                }
                return ['id' => 'test-barrister-email'];
            },
        );

        $warning = (new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($this->order(), $this->lines());

        $this->assertNotNull($warning);
        $this->assertSame(['team@example.com'], $messages[0]['to']);
        $this->assertSame(['barrister@example.com'], $messages[1]['to']);
        $this->assertSame('Your dry cleaning request', $messages[1]['subject']);
    }

    public function testBothMessagesHaveGreetingSubmittedItemsAndFinalTotal(): void
    {
        $this->saveMailbox('team@example.com');
        $messages = [];
        $mailer = $this->recordingMailer(2, $messages);

        $warning = (new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($this->order(), $this->lines());

        $this->assertNull($warning);
        $this->assertSame(['team@example.com'], $messages[0]['to']);
        $this->assertSame('New dry cleaning request', $messages[0]['subject']);
        $this->assertStringStartsWith("Dear team,\n\n", $messages[0]['body']);
        $this->assertStringContainsString('barrister@example.com', $messages[0]['body']);
        $this->assertSame(['barrister@example.com'], $messages[1]['to']);
        $this->assertSame('Your dry cleaning request', $messages[1]['subject']);
        $this->assertStringStartsWith("Dear barrister,\n\n", $messages[1]['body']);
        foreach ($messages as $message) {
            $this->assertStringContainsString('- Suit jacket: 2 x $18.00 = $36.00', $message['body']);
            $this->assertStringContainsString('- Shirt: 1 x $6.00 = $6.00', $message['body']);
            $this->assertStringContainsString('Final price: $37.80', $message['body']);
            $this->assertSame('text', $message['format']);
        }
    }

    public function testMissingNominatedMailboxStillSendsBarristerMessageAndReturnsWarning(): void
    {
        $messages = [];
        $mailer = $this->recordingMailer(1, $messages);

        $warning = (new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($this->order(), $this->lines());

        $this->assertNotNull($warning);
        $this->assertStringContainsString('Your request was saved', $warning);
        $this->assertSame(['barrister@example.com'], $messages[0]['to']);
        $this->assertSame('Your dry cleaning request', $messages[0]['subject']);
    }

    public function testSameMailboxReceivesBothDistinctMessages(): void
    {
        $this->saveMailbox('barrister@example.com');
        $messages = [];
        $mailer = $this->recordingMailer(2, $messages);

        $warning = (new NotificationService(static fn (): Mailer => $mailer))->sendRequestSubmitted($this->order(), $this->lines());

        $this->assertNull($warning);
        $this->assertSame(['barrister@example.com'], $messages[0]['to']);
        $this->assertSame(['barrister@example.com'], $messages[1]['to']);
        $this->assertSame(['New dry cleaning request', 'Your dry cleaning request'], array_column($messages, 'subject'));
    }

    private function recordingMailer(int $expectedMessages, array &$messages): Mailer
    {
        $mailer = $this->getMockBuilder(Mailer::class)->setConstructorArgs([[]])->onlyMethods(['deliver'])->getMock();
        $mailer->expects($this->exactly($expectedMessages))->method('deliver')->willReturnCallback(
            static function (string $body) use ($mailer, &$messages): array {
                $messages[] = [
                    'to' => array_keys($mailer->getTo()),
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
        return (object)['id' => 17, 'email' => 'barrister@example.com', 'total_cents' => 3780];
    }

    private function lines(): array
    {
        return [
            ['item_name' => 'Suit jacket', 'quantity' => 2, 'unit_price_cents' => 1800, 'line_total_cents' => 3600],
            ['item_name' => 'Shirt', 'quantity' => 1, 'unit_price_cents' => 600, 'line_total_cents' => 600],
        ];
    }
}

