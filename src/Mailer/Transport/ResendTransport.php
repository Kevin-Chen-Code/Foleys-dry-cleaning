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
    private Client $client;

    protected array $_defaultConfig = [
        'apiKey' => '',
        'timeout' => 15,
    ];

    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config);
        $this->client = $client ?? new Client(['timeout' => (int)$this->getConfig('timeout')]);
    }

    public function send(Message $message): array
    {
        $this->checkRecipient($message);
        $apiKey = trim((string)$this->getConfig('apiKey'));
        if ($apiKey === '') {
            throw new CakeException('RESEND_API_KEY is not configured.');
        }

        $senders = $message->getFrom();
        if ($senders === []) {
            throw new CakeException('EMAIL_FROM is not configured.');
        }
        $address = (string)array_key_first($senders);
        $name = (string)$senders[$address];
        $from = $name !== '' ? $name . ' <' . $address . '>' : $address;

        $payload = [
            'from' => $from,
            'to' => array_keys($message->getTo()),
            'subject' => $message->getSubject(),
        ];
        // Send actual text/HTML rather than the MIME-encoded body and boundaries.
        if ($message->getBodyText() !== '') {
            $payload['text'] = $message->getBodyText();
        }
        if ($message->getBodyHtml() !== '') {
            $payload['html'] = $message->getBodyHtml();
        }
        foreach (['cc' => $message->getCc(), 'bcc' => $message->getBcc(), 'reply_to' => $message->getReplyTo()] as $key => $addresses) {
            if ($addresses !== []) {
                $payload[$key] = array_keys($addresses);
            }
        }
        foreach ($message->getAttachments() as $filename => $attachment) {
            if (isset($attachment['data'])) {
                // CakePHP has already base64-encoded data attachments.
                $content = preg_replace('/\s+/', '', $attachment['data']);
            } else {
                $file = $attachment['file'];
                $data = is_string($file) ? file_get_contents($file) : (string)$file->getStream();
                if ($data === false) {
                    throw new CakeException('An email attachment could not be read.');
                }
                $content = base64_encode($data);
            }
            $payload['attachments'][] = ['filename' => (string)$filename, 'content' => $content];
        }

        $response = $this->client->post(
            'https://api.resend.com/emails',
            $payload,
            [
                'type' => 'json',
                'headers' => ['Authorization' => 'Bearer ' . $apiKey],
            ],
        );
        $responseData = $response->getJson();
        if (!$response->isOk()) {
            $reason = is_array($responseData) ? ($responseData['message'] ?? 'No explanation returned.') : 'No explanation returned.';
            $reason = str_replace($apiKey, '[redacted]', (string)$reason);
            $reason = preg_replace('/[\x00-\x1F\x7F]/', ' ', $reason);
            throw new CakeException(sprintf('Resend rejected the email (HTTP %d): %s', $response->getStatusCode(), substr($reason, 0, 500)));
        }
        if (!is_array($responseData) || !is_string($responseData['id'] ?? null) || $responseData['id'] === '') {
            throw new CakeException('Resend did not return an email ID; acceptance could not be confirmed.');
        }

        return [
            'headers' => $message->getHeadersString(),
            'message' => $message->getBodyString(),
            'id' => $responseData['id'],
        ];
    }
}
