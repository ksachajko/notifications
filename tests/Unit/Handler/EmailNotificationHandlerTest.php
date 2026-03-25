<?php

declare(strict_types=1);

namespace App\Tests\Unit\Handler;

use App\Audit\AuditLogger;
use App\Handler\EmailNotificationHandler;
use App\Message\EmailNotificationMessage;
use App\Provider\Email\EmailProviderInterface;
use App\Provider\ProviderException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EmailNotificationHandlerTest extends TestCase
{
    private EmailNotificationMessage $message;

    protected function setUp(): void
    {
        $this->message = new EmailNotificationMessage(
            correlationId: 'test-correlation-id',
            user: 1,
            to: 'user@example.com',
            subject: 'Hello',
            body: 'Test body',
        );
    }

    private function makeHandler(array $providers, ?LoggerInterface $logger = null, ?AuditLogger $auditLogger = null): EmailNotificationHandler
    {
        return new EmailNotificationHandler(
            $providers,
            $logger ?? $this->createMock(LoggerInterface::class),
            $auditLogger ?? $this->createMock(AuditLogger::class),
        );
    }

    public function testFirstProviderSucceeds(): void
    {
        $provider = $this->createMock(EmailProviderInterface::class);
        $provider->expects($this->once())->method('send');

        $this->makeHandler([$provider])($this->message);
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

        $this->makeHandler([$failing, $succeeding], $logger)($this->message);
    }

    public function testThrowsWhenAllProvidersFail(): void
    {
        $provider1 = $this->createMock(EmailProviderInterface::class);
        $provider1->method('send')->willThrowException(new ProviderException('fail'));

        $provider2 = $this->createMock(EmailProviderInterface::class);
        $provider2->method('send')->willThrowException(new ProviderException('fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All email providers failed for recipient user@example.com');

        $this->makeHandler([$provider1, $provider2], $logger)($this->message);
    }

    public function testThrowsWhenNoProviders(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makeHandler([])($this->message);
    }

    public function testSecondProviderNotCalledWhenFirstSucceeds(): void
    {
        $first = $this->createMock(EmailProviderInterface::class);
        $first->expects($this->once())->method('send');

        $second = $this->createMock(EmailProviderInterface::class);
        $second->expects($this->never())->method('send');

        $this->makeHandler([$first, $second])($this->message);
    }

    public function testAuditLogsProviderSuccess(): void
    {
        $provider = $this->createMock(EmailProviderInterface::class);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())
            ->method('logProviderSuccess')
            ->with('test-correlation-id', 'email', get_class($provider), 1, 'user@example.com');

        $this->makeHandler([$provider], auditLogger: $auditLogger)($this->message);
    }

    public function testAuditLogsProviderFailureThenSuccess(): void
    {
        $failing = $this->createMock(EmailProviderInterface::class);
        $failing->method('send')->willThrowException(new ProviderException('SES down'));

        $succeeding = $this->createMock(EmailProviderInterface::class);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())->method('logProviderFailure')
            ->with('test-correlation-id', 'email', get_class($failing), 1, 'user@example.com', 'SES down');
        $auditLogger->expects($this->once())->method('logProviderSuccess')
            ->with('test-correlation-id', 'email', get_class($succeeding), 1, 'user@example.com');

        $this->makeHandler([$failing, $succeeding], auditLogger: $auditLogger)($this->message);
    }

    public function testAuditLogsAllProvidersFailedBeforeException(): void
    {
        $provider = $this->createMock(EmailProviderInterface::class);
        $provider->method('send')->willThrowException(new ProviderException('fail'));

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())->method('logAllProvidersFailed')
            ->with('test-correlation-id', 'email', 1, 'user@example.com');

        try {
            $this->makeHandler([$provider], auditLogger: $auditLogger)($this->message);
        } catch (\RuntimeException) {
        }
    }
}
