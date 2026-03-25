<?php

declare(strict_types=1);

namespace App\Handler;

use App\Audit\AuditLogger;
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
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(EmailNotificationMessage $message): void
    {
        foreach ($this->providers as $provider) {
            try {
                $provider->send($message);

                $this->auditLogger->logProviderSuccess(
                    $message->correlationId,
                    'email',
                    get_class($provider),
                    $message->user,
                    $message->to,
                );

                return;
            } catch (ProviderException $e) {
                $this->logger->warning('Email provider failed, trying next', [
                    'provider' => get_class($provider),
                    'error' => $e->getMessage(),
                    'to' => $message->to,
                ]);

                $this->auditLogger->logProviderFailure(
                    $message->correlationId,
                    'email',
                    get_class($provider),
                    $message->user,
                    $message->to,
                    $e->getMessage(),
                );
            }
        }

        $this->auditLogger->logAllProvidersFailed(
            $message->correlationId,
            'email',
            $message->user,
            $message->to,
        );

        throw new \RuntimeException(sprintf('All email providers failed for recipient %s', $message->to));
    }
}
