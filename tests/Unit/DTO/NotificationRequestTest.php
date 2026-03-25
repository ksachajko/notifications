<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\NotificationRequest;
use PHPUnit\Framework\TestCase;

class NotificationRequestTest extends TestCase
{
    private array $enabledChannels = ['email' => true, 'sms' => true];

    public function testFromArrayPopulatesFields(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 42,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg']],
        ], $this->enabledChannels);

        $this->assertSame(42, $dto->user);
        $this->assertSame(['email'], $dto->channels);
    }

    public function testIsChannelsValidReturnsTrueForEnabledChannel(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => ['email' => []],
        ], $this->enabledChannels);

        $this->assertTrue($dto->isChannelsValid());
    }

    public function testIsChannelsValidReturnsFalseForDisabledChannel(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['sms'],
            'data' => ['sms' => []],
        ], ['email' => true, 'sms' => false]);

        $this->assertFalse($dto->isChannelsValid());
    }

    public function testIsChannelsValidReturnsFalseForUnknownChannel(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['push'],
            'data' => ['push' => []],
        ], $this->enabledChannels);

        $this->assertFalse($dto->isChannelsValid());
    }

    public function testIsDataValidReturnsTrueWhenAllChannelDataPresent(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email', 'sms'],
            'data' => [
                'email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg'],
                'sms' => ['to' => '+1234567890', 'body' => 'Msg'],
            ],
        ], $this->enabledChannels);

        $this->assertTrue($dto->isDataValid());
    }

    public function testIsDataValidReturnsFalseWhenChannelDataMissing(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => [],
        ], $this->enabledChannels);

        $this->assertFalse($dto->isDataValid());
    }

    public function testGetEmailDataReturnsNullWhenMissing(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['sms'],
            'data' => ['sms' => ['to' => '+1', 'body' => 'hi']],
        ], $this->enabledChannels);

        $this->assertNull($dto->getEmailData());
    }

    public function testGetEmailDataReturnsParsedData(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg']],
        ], $this->enabledChannels);

        $emailData = $dto->getEmailData();
        $this->assertNotNull($emailData);
        $this->assertSame('a@b.com', $emailData->to);
        $this->assertSame('Hi', $emailData->subject);
        $this->assertSame('Msg', $emailData->body);
    }

    public function testGetSmsDataReturnsNullWhenMissing(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg']],
        ], $this->enabledChannels);

        $this->assertNull($dto->getSmsData());
    }

    public function testGetSmsDataReturnsParsedData(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['sms'],
            'data' => ['sms' => ['to' => '+1234567890', 'body' => 'Hello']],
        ], $this->enabledChannels);

        $smsData = $dto->getSmsData();
        $this->assertNotNull($smsData);
        $this->assertSame('+1234567890', $smsData->to);
        $this->assertSame('Hello', $smsData->body);
    }

    public function testUserDefaultsToZeroWhenInvalid(): void
    {
        $dto = NotificationRequest::fromArray([
            'user' => 'not-an-int',
            'channels' => ['email'],
            'data' => [],
        ], $this->enabledChannels);

        $this->assertSame(0, $dto->user);
    }
}
