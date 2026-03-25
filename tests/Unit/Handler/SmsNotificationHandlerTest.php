<?php

declare(strict_types=1);

namespace App\Tests\Unit\Handler;

use App\Handler\SmsNotificationHandler;
use App\Message\SmsNotificationMessage;
use App\Provider\ProviderException;
use App\Provider\Sms\SmsProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SmsNotificationHandlerTest extends TestCase
{
    private SmsNotificationMessage $message;

    protected function setUp(): void
    {
        $this->message = new SmsNotificationMessage(
            user: 1,
            to: '+1234567890',
            body: 'Test SMS',
        );
    }

    public function testFirstProviderSucceeds(): void
    {
        $provider = $this->createMock(SmsProviderInterface::class);
        $provider->expects($this->once())->method('send');

        $handler = new SmsNotificationHandler([$provider], $this->createMock(LoggerInterface::class));
        $handler($this->message);
    }

    public function testSecondProviderUsedWhenFirstFails(): void
    {
        $failing = $this->createMock(SmsProviderInterface::class);
        $failing->expects($this->once())
            ->method('send')
            ->willThrowException(new ProviderException('Provider 1 down'));

        $succeeding = $this->createMock(SmsProviderInterface::class);
        $succeeding->expects($this->once())->method('send');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $handler = new SmsNotificationHandler([$failing, $succeeding], $logger);
        $handler($this->message);
    }

    public function testThrowsWhenAllProvidersFail(): void
    {
        $provider1 = $this->createMock(SmsProviderInterface::class);
        $provider1->method('send')->willThrowException(new ProviderException('fail'));

        $provider2 = $this->createMock(SmsProviderInterface::class);
        $provider2->method('send')->willThrowException(new ProviderException('fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $handler = new SmsNotificationHandler([$provider1, $provider2], $logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All SMS providers failed for recipient +1234567890');

        $handler($this->message);
    }

    public function testThrowsWhenNoProviders(): void
    {
        $handler = new SmsNotificationHandler([], $this->createMock(LoggerInterface::class));

        $this->expectException(\RuntimeException::class);

        $handler($this->message);
    }

    public function testSecondProviderNotCalledWhenFirstSucceeds(): void
    {
        $first = $this->createMock(SmsProviderInterface::class);
        $first->expects($this->once())->method('send');

        $second = $this->createMock(SmsProviderInterface::class);
        $second->expects($this->never())->method('send');

        $handler = new SmsNotificationHandler([$first, $second], $this->createMock(LoggerInterface::class));
        $handler($this->message);
    }
}
