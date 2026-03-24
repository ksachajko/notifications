<?php

declare(strict_types=1);

namespace App\Provider\Sms;

use App\Message\SmsNotificationMessage;

interface SmsProviderInterface
{
    public function send(SmsNotificationMessage $message): void;
}
