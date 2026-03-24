<?php

declare(strict_types=1);

namespace App\Handler;

use App\Message\EmailNotificationMessage;
use App\Provider\Email\EmailProviderInterface;
use App\Provider\ProviderException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EmailNotificationHandler
{
    /** @param EmailProviderInterface[] $providers */
    public function __construct(
        private readonly array $providers,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EmailNotificationMessage $message): void
    {
        foreach ($this->providers as $provider) {
            try {
                $provider->send($message);
                return;
            } catch (ProviderException $e) {
                $this->logger->warning('Email provider failed, trying next', [
                    'provider' => get_class($provider),
                    'error'    => $e->getMessage(),
                    'to'       => $message->to,
                ]);
            }
        }

        throw new \RuntimeException(sprintf(
            'All email providers failed for recipient %s',
            $message->to,
        ));
    }
}
