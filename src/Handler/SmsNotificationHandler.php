<?php

declare(strict_types=1);

namespace App\Handler;

use App\Message\SmsNotificationMessage;
use App\Provider\ProviderException;
use App\Provider\Sms\SmsProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SmsNotificationHandler
{
    /** @param SmsProviderInterface[] $providers */
    public function __construct(
        private readonly array $providers,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SmsNotificationMessage $message): void
    {
        foreach ($this->providers as $provider) {
            try {
                $provider->send($message);

                return;
            } catch (ProviderException $e) {
                $this->logger->warning('SMS provider failed, trying next', [
                    'provider' => get_class($provider),
                    'error' => $e->getMessage(),
                    'to' => $message->to,
                ]);
            }
        }

        throw new \RuntimeException(sprintf('All SMS providers failed for recipient %s', $message->to));
    }
}
