<?php
declare(strict_types=1);

namespace App\Mailer\Transport;

use Cake\Core\Exception\CakeException;
use Cake\Http\Client;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Message;

/** Sends transactional emails through Resend's HTTPS API, which works on Render Free. */
final class ResendTransport extends AbstractTransport
{
    protected array $_defaultConfig = [
        'apiKey' => '',
        'timeout' => 15,
    ];

    public function send(Message $message): array
    {
        $this->checkRecipient($message);
        $apiKey = (string)$this->getConfig('apiKey');
        if ($apiKey === '') {
            throw new CakeException('RESEND_API_KEY is not configured.');
        }

        $headers = $message->getHeaders(['from']);
        $from = (string)($headers['From'] ?? '');
        if ($from === '') {
            throw new CakeException('EMAIL_FROM is not configured.');
        }

        $response = (new Client(['timeout' => (int)$this->getConfig('timeout')]))->post(
            'https://api.resend.com/emails',
            [
                'from' => $from,
                'to' => array_keys($message->getTo()),
                'subject' => $message->getSubject(),
                'text' => $message->getBodyString(),
            ],
            [
                'type' => 'json',
                'headers' => ['Authorization' => 'Bearer ' . $apiKey],
            ],
        );
        if (!$response->isOk()) {
            throw new CakeException('Resend rejected the email: ' . $response->getStringBody());
        }

        $responseData = $response->getJson();

        return [
            'headers' => $message->getHeadersString(),
            'message' => $message->getBodyString(),
            'id' => is_array($responseData) ? ($responseData['id'] ?? null) : null,
        ];
    }
}
