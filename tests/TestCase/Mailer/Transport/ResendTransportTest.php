<?php
declare(strict_types=1);

namespace App\Test\TestCase\Mailer\Transport;

use App\Mailer\Transport\ResendTransport;
use Cake\Core\Exception\CakeException;
use Cake\Mailer\Message;
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
}
