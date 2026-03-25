<?php

declare(strict_types=1);

namespace App\Audit;

use Doctrine\ORM\EntityManagerInterface;

class AuditLogger
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function logRequestReceived(string $correlationId, int $userId, array $channels): void
    {
        $this->save(new NotificationAudit(
            correlationId: $correlationId,
            eventType: 'request_received',
            userId: $userId,
        ));
    }

    public function logProviderSuccess(string $correlationId, string $channel, string $provider, int $userId, string $recipient): void
    {
        $this->save(new NotificationAudit(
            correlationId: $correlationId,
            eventType: 'provider_success',
            userId: $userId,
            channel: $channel,
            provider: $provider,
            recipient: $recipient,
        ));
    }

    public function logProviderFailure(string $correlationId, string $channel, string $provider, int $userId, string $recipient, string $error): void
    {
        $this->save(new NotificationAudit(
            correlationId: $correlationId,
            eventType: 'provider_failure',
            userId: $userId,
            channel: $channel,
            provider: $provider,
            recipient: $recipient,
            error: $error,
        ));
    }

    public function logAllProvidersFailed(string $correlationId, string $channel, int $userId, string $recipient): void
    {
        $this->save(new NotificationAudit(
            correlationId: $correlationId,
            eventType: 'all_providers_failed',
            userId: $userId,
            channel: $channel,
            recipient: $recipient,
        ));
    }

    private function save(NotificationAudit $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
