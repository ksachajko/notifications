<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Audit\AuditLogger;
use App\DTO\NotificationRequest;
use App\Message\EmailNotificationMessage;
use App\Message\SmsNotificationMessage;
use App\Service\NotificationDispatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class NotificationDispatcherTest extends TestCase
{
    private array $enabledChannels = ['email' => true, 'sms' => true];

    private function makeDispatcher(MessageBusInterface $bus): NotificationDispatcher
    {
        return new NotificationDispatcher($bus, $this->enabledChannels, $this->createMock(AuditLogger::class));
    }

    private function makeBusThatExpects(string $messageClass): MessageBusInterface
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf($messageClass))
            ->willReturn(new Envelope(new \stdClass()));

        return $bus;
    }

    public function testDispatchesEmailMessage(): void
    {
        $dispatcher = $this->makeDispatcher($this->makeBusThatExpects(EmailNotificationMessage::class));

        $dispatcher->dispatch(NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg']],
        ], $this->enabledChannels));
    }

    public function testDispatchesSmsMessage(): void
    {
        $dispatcher = $this->makeDispatcher($this->makeBusThatExpects(SmsNotificationMessage::class));

        $dispatcher->dispatch(NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['sms'],
            'data' => ['sms' => ['to' => '+1234567890', 'body' => 'Hello']],
        ], $this->enabledChannels));
    }

    public function testDispatchesBothChannels(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->makeDispatcher($bus)->dispatch(NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email', 'sms'],
            'data' => [
                'email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg'],
                'sms' => ['to' => '+1234567890', 'body' => 'Hello'],
            ],
        ], $this->enabledChannels));
    }

    public function testDoesNotDispatchEmailWhenDataMissing(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $this->makeDispatcher($bus)->dispatch(NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => [],
        ], $this->enabledChannels));
    }

    public function testEmailMessageCarriesCorrectPayload(): void
    {
        $capturedMessage = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturnCallback(function ($message) use (&$capturedMessage) {
                $capturedMessage = $message;

                return new Envelope($message);
            });

        $this->makeDispatcher($bus)->dispatch(NotificationRequest::fromArray([
            'user' => 42,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'test@example.com', 'subject' => 'Subject', 'body' => 'Body']],
        ], $this->enabledChannels));

        $this->assertInstanceOf(EmailNotificationMessage::class, $capturedMessage);
        $this->assertSame(42, $capturedMessage->user);
        $this->assertSame('test@example.com', $capturedMessage->to);
        $this->assertSame('Subject', $capturedMessage->subject);
        $this->assertSame('Body', $capturedMessage->body);
        $this->assertNotEmpty($capturedMessage->correlationId);
    }

    public function testAuditLoggerReceivesRequestReceived(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())
            ->method('logRequestReceived')
            ->with($this->matchesRegularExpression('/^[0-9a-f\-]{36}$/'), 1, ['email']);

        $dispatcher = new NotificationDispatcher($bus, $this->enabledChannels, $auditLogger);
        $dispatcher->dispatch(NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg']],
        ], $this->enabledChannels));
    }

    public function testCorrelationIdIsAttachedToMessage(): void
    {
        $capturedMessage = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function ($msg) use (&$capturedMessage) {
            $capturedMessage = $msg;

            return new Envelope($msg);
        });

        $capturedCorrelationId = null;
        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->method('logRequestReceived')
            ->willReturnCallback(function (string $correlationId) use (&$capturedCorrelationId) {
                $capturedCorrelationId = $correlationId;
            });

        $dispatcher = new NotificationDispatcher($bus, $this->enabledChannels, $auditLogger);
        $dispatcher->dispatch(NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg']],
        ], $this->enabledChannels));

        $this->assertNotNull($capturedCorrelationId);
        $this->assertSame($capturedCorrelationId, $capturedMessage->correlationId);
    }
}
