<?php

declare(strict_types=1);

namespace App\Handler;

use App\Audit\AuditLogger;
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
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function __invoke(SmsNotificationMessage $message): void
    {
        foreach ($this->providers as $provider) {
            try {
                $provider->send($message);

                $this->auditLogger->logProviderSuccess(
                    $message->correlationId,
                    'sms',
                    get_class($provider),
                    $message->user,
                    $message->to,
                );

                return;
            } catch (ProviderException $e) {
                $this->logger->warning('SMS provider failed, trying next', [
                    'provider' => get_class($provider),
                    'error' => $e->getMessage(),
                    'to' => $message->to,
                ]);

                $this->auditLogger->logProviderFailure(
                    $message->correlationId,
                    'sms',
                    get_class($provider),
                    $message->user,
                    $message->to,
                    $e->getMessage(),
                );
            }
        }

        $this->auditLogger->logAllProvidersFailed(
            $message->correlationId,
            'sms',
            $message->user,
            $message->to,
        );

        throw new \RuntimeException(sprintf('All SMS providers failed for recipient %s', $message->to));
    }
}
