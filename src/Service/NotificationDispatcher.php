<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\NotificationRequest;
use App\Message\EmailNotificationMessage;
use App\Message\SmsNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class NotificationDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly array $enabledChannels,
    ) {
    }

    public function dispatch(NotificationRequest $request): void
    {
        foreach ($request->channels as $channel) {
            match ($channel) {
                'email' => $this->dispatchEmail($request),
                'sms' => $this->dispatchSms($request),
                default => null,
            };
        }
    }

    private function dispatchEmail(NotificationRequest $request): void
    {
        $data = $request->getEmailData();
        if ($data === null) {
            return;
        }

        $this->bus->dispatch(new EmailNotificationMessage(
            user: $request->user,
            to: $data->to,
            subject: $data->subject,
            body: $data->body,
        ));
    }

    private function dispatchSms(NotificationRequest $request): void
    {
        $data = $request->getSmsData();
        if ($data === null) {
            return;
        }

        $this->bus->dispatch(new SmsNotificationMessage(
            user: $request->user,
            to: $data->to,
            body: $data->body,
        ));
    }
}
