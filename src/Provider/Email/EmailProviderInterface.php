<?php

declare(strict_types=1);

namespace App\Provider\Email;

use App\Message\EmailNotificationMessage;

interface EmailProviderInterface
{
    public function send(EmailNotificationMessage $message): void;
}
