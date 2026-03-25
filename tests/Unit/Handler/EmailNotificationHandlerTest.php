<?php

declare(strict_types=1);

namespace App\Tests\Unit\Handler;

use App\Handler\EmailNotificationHandler;
use App\Message\EmailNotificationMessage;
use App\Provider\Email\EmailProviderInterface;
use App\Provider\ProviderException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EmailNotificationHandlerTest extends TestCase
{
    private EmailNotificationMessage $message;

    protected function setUp(): void
    {
        $this->message = new EmailNotificationMessage(
            user: 1,
            to: 'user@example.com',
            subject: 'Hello',
            body: 'Test body',
        );
    }

    public function testFirstProviderSucceeds(): void
    {
        $provider = $this->createMock(EmailProviderInterface::class);
        $provider->expects($this->once())->method('send');

        $handler = new EmailNotificationHandler([$provider], $this->createMock(LoggerInterface::class));
        $handler($this->message);
    }

    public function testSecondProviderUsedWhenFirstFails(): void
    {
        $failing = $this->createMock(EmailProviderInterface::class);
        $failing->expects($this->once())
            ->method('send')
            ->willThrowException(new ProviderException('SES down'));

        $succeeding = $this->createMock(EmailProviderInterface::class);
        $succeeding->expects($this->once())->method('send');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $handler = new EmailNotificationHandler([$failing, $succeeding], $logger);
        $handler($this->message);
    }

    public function testThrowsWhenAllProvidersFail(): void
    {
        $provider1 = $this->createMock(EmailProviderInterface::class);
        $provider1->method('send')->willThrowException(new ProviderException('fail'));

        $provider2 = $this->createMock(EmailProviderInterface::class);
        $provider2->method('send')->willThrowException(new ProviderException('fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $handler = new EmailNotificationHandler([$provider1, $provider2], $logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All email providers failed for recipient user@example.com');

        $handler($this->message);
    }

    public function testThrowsWhenNoProviders(): void
    {
        $handler = new EmailNotificationHandler([], $this->createMock(LoggerInterface::class));

        $this->expectException(\RuntimeException::class);

        $handler($this->message);
    }

    public function testSecondProviderNotCalledWhenFirstSucceeds(): void
    {
        $first = $this->createMock(EmailProviderInterface::class);
        $first->expects($this->once())->method('send');

        $second = $this->createMock(EmailProviderInterface::class);
        $second->expects($this->never())->method('send');

        $handler = new EmailNotificationHandler([$first, $second], $this->createMock(LoggerInterface::class));
        $handler($this->message);
    }
}
