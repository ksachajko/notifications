<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

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
        $bus = $this->makeBusThatExpects(EmailNotificationMessage::class);
        $dispatcher = new NotificationDispatcher($bus, $this->enabledChannels);

        $request = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg']],
        ], $this->enabledChannels);

        $dispatcher->dispatch($request);
    }

    public function testDispatchesSmsMessage(): void
    {
        $bus = $this->makeBusThatExpects(SmsNotificationMessage::class);
        $dispatcher = new NotificationDispatcher($bus, $this->enabledChannels);

        $request = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['sms'],
            'data' => ['sms' => ['to' => '+1234567890', 'body' => 'Hello']],
        ], $this->enabledChannels);

        $dispatcher->dispatch($request);
    }

    public function testDispatchesBothChannels(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $dispatcher = new NotificationDispatcher($bus, $this->enabledChannels);

        $request = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email', 'sms'],
            'data' => [
                'email' => ['to' => 'a@b.com', 'subject' => 'Hi', 'body' => 'Msg'],
                'sms' => ['to' => '+1234567890', 'body' => 'Hello'],
            ],
        ], $this->enabledChannels);

        $dispatcher->dispatch($request);
    }

    public function testDoesNotDispatchEmailWhenDataMissing(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $dispatcher = new NotificationDispatcher($bus, $this->enabledChannels);

        $request = NotificationRequest::fromArray([
            'user' => 1,
            'channels' => ['email'],
            'data' => [],
        ], $this->enabledChannels);

        $dispatcher->dispatch($request);
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

        $dispatcher = new NotificationDispatcher($bus, $this->enabledChannels);

        $request = NotificationRequest::fromArray([
            'user' => 42,
            'channels' => ['email'],
            'data' => ['email' => ['to' => 'test@example.com', 'subject' => 'Subject', 'body' => 'Body']],
        ], $this->enabledChannels);

        $dispatcher->dispatch($request);

        $this->assertInstanceOf(EmailNotificationMessage::class, $capturedMessage);
        $this->assertSame(42, $capturedMessage->user);
        $this->assertSame('test@example.com', $capturedMessage->to);
        $this->assertSame('Subject', $capturedMessage->subject);
        $this->assertSame('Body', $capturedMessage->body);
    }
}
