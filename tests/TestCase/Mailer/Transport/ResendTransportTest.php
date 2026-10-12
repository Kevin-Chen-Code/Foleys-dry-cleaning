<?php
declare(strict_types=1);

namespace App\Test\TestCase\Mailer\Transport;

use App\Mailer\Transport\ResendTransport;
use Cake\Core\Exception\CakeException;
use Cake\Mailer\Message;
use Cake\Http\Client;
use Cake\Http\Client\Response;
use Cake\TestSuite\TestCase;

final class ResendTransportTest extends TestCase
{
    public function testMissingApiKeyExplainsHowToEnableDelivery(): void
    {
        $message = (new Message())->setTo('barrister@example.com');

        $this->expectException(CakeException::class);
        $this->expectExceptionMessage('RESEND_API_KEY is not configured.');

        (new ResendTransport())->send($message);
    }

    public function testResendReceivesPlainTextAndCsvAttachment(): void
    {
        $csv = "Item,Quantity\nSuit,2\n";
        $message = (new Message())
            ->setFrom('sender@example.com', "Foley's List")
            ->setTo('team@example.com')
            ->setSubject('Weekly report')
            ->setBodyText("Dear team,\nPlease see the attached report.")
            ->setAttachments(['report.csv' => ['data' => $csv, 'mimetype' => 'text/csv']]);
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('post')->with(
            'https://api.resend.com/emails',
            $this->callback(function (array $payload) use ($csv): bool {
                $this->assertSame("Foley's List <sender@example.com>", $payload['from']);
                $this->assertSame(['team@example.com'], $payload['to']);
                $this->assertSame("Dear team,\nPlease see the attached report.", $payload['text']);
                $this->assertSame('report.csv', $payload['attachments'][0]['filename']);
                $this->assertSame($csv, base64_decode($payload['attachments'][0]['content'], true));
                $this->assertArrayNotHasKey('html', $payload);
                return true;
            }),
            ['type' => 'json', 'headers' => ['Authorization' => 'Bearer test-key']],
        )->willReturn(new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'], '{"id":"accepted-email-id"}'));
        $result = (new ResendTransport(['apiKey' => 'test-key'], $client))->send($message);
        $this->assertSame('accepted-email-id', $result['id']);
    }

    public function testDomainRejectionExplainsCauseWithoutLeakingApiKey(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('post')->willReturn(new Response(
            ['HTTP/1.1 403 Forbidden', 'Content-Type: application/json'],
            '{"message":"Domain not verified. test-secret"}',
        ));
        $message = (new Message())->setFrom('sender@example.com')->setTo('team@example.com')->setBodyText('Hello');
        try {
            (new ResendTransport(['apiKey' => 'test-secret'], $client))->send($message);
            $this->fail('Provider rejection must not be treated as success.');
        } catch (CakeException $exception) {
            $this->assertStringContainsString('HTTP 403', $exception->getMessage());
            $this->assertStringContainsString('Domain not verified', $exception->getMessage());
            $this->assertStringNotContainsString('test-secret', $exception->getMessage());
        }
    }

    public function testSuccessResponseWithoutEmailIdIsNotAccepted(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())->method('post')->willReturn(new Response(['HTTP/1.1 200 OK', 'Content-Type: application/json'], '{}'));
        $this->expectException(CakeException::class);
        $this->expectExceptionMessage('Resend did not return an email ID');
        (new ResendTransport(['apiKey' => 'test-key'], $client))->send(
            (new Message())->setFrom('sender@example.com')->setTo('team@example.com')->setBodyText('Hello'),
        );
    }
}
