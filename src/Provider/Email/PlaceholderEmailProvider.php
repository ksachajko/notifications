<?php

declare(strict_types=1);

namespace App\Provider\Email;

use App\Message\EmailNotificationMessage;
use App\Provider\ProviderException;
use Psr\Log\LoggerInterface;

class PlaceholderEmailProvider implements EmailProviderInterface
{
    /**
     * Set to true to simulate provider failure (useful for testing failover).
     */
    private bool $shouldFail = false;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function send(EmailNotificationMessage $message): void
    {
        if ($this->shouldFail) {
            throw new ProviderException('PlaceholderEmailProvider: simulated failure');
        }

        $this->logger->info('PlaceholderEmailProvider: email sent (no-op)', [
            'to'      => $message->to,
            'subject' => $message->subject,
        ]);
    }
}
