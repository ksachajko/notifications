<?php

declare(strict_types=1);

namespace App\Service;

use App\Audit\AuditLogger;
use App\DTO\NotificationRequest;
use App\Message\EmailNotificationMessage;
use App\Message\SmsNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class NotificationDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly array $enabledChannels,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function dispatch(NotificationRequest $request): void
    {
        $correlationId = (string) Uuid::v4();

        $this->auditLogger->logRequestReceived($correlationId, $request->user, $request->channels);

        foreach ($request->channels as $channel) {
            match ($channel) {
                'email' => $this->dispatchEmail($request, $correlationId),
                'sms' => $this->dispatchSms($request, $correlationId),
                default => null,
            };
        }
    }

    private function dispatchEmail(NotificationRequest $request, string $correlationId): void
    {
        $data = $request->getEmailData();
        if ($data === null) {
            return;
        }

        $this->bus->dispatch(new EmailNotificationMessage(
            correlationId: $correlationId,
            user: $request->user,
            to: $data->to,
            subject: $data->subject,
            body: $data->body,
        ));
    }

    private function dispatchSms(NotificationRequest $request, string $correlationId): void
    {
        $data = $request->getSmsData();
        if ($data === null) {
            return;
        }

        $this->bus->dispatch(new SmsNotificationMessage(
            correlationId: $correlationId,
            user: $request->user,
            to: $data->to,
            body: $data->body,
        ));
    }
}
