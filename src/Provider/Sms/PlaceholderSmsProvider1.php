<?php

declare(strict_types=1);

namespace App\Provider\Sms;

use App\Message\SmsNotificationMessage;
use App\Provider\ProviderException;
use Psr\Log\LoggerInterface;

class PlaceholderSmsProvider1 implements SmsProviderInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function send(SmsNotificationMessage $message): void
    {
        $this->logger->info('PlaceholderSmsProvider1: SMS sent (no-op)', [
            'to' => $message->to,
        ]);

        throw new ProviderException('PlaceholderSmsProvider1: simulated failure');
    }
}
