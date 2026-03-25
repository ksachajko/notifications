<?php

declare(strict_types=1);

namespace App\Provider\Email;

use App\Message\EmailNotificationMessage;
use App\Provider\ProviderException;
use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Psr\Log\LoggerInterface;

class SesEmailProvider implements EmailProviderInterface
{
    private SesV2Client $client;

    public function __construct(
        private readonly string $fromEmail,
        private readonly LoggerInterface $logger,
        string $region,
        string $accessKeyId,
        string $secretAccessKey,
    ) {
        $this->client = new SesV2Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ],
        ]);
    }

    public function send(EmailNotificationMessage $message): void
    {
        try {
            $this->client->sendEmail([
                'FromEmailAddress' => $this->fromEmail,
                'Destination' => [
                    'ToAddresses' => [$message->to],
                ],
                'Content' => [
                    'Simple' => [
                        'Subject' => ['Data' => $message->subject],
                        'Body' => ['Text' => ['Data' => $message->body]],
                    ],
                ],
            ]);

            $this->logger->info('SES email sent', ['to' => $message->to]);
        } catch (AwsException $e) {
            throw new ProviderException(sprintf('SES failed: %s', $e->getMessage()), 0, $e);
        }
    }
}
