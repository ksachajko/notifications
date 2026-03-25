<?php

declare(strict_types=1);

namespace App\Message;

final class EmailNotificationMessage
{
    public function __construct(
        public readonly string $correlationId,
        public readonly int $user,
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
    ) {
    }
}
