<?php

declare(strict_types=1);

namespace App\Tests\Unit\Handler;

use App\Audit\AuditLogger;
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
            correlationId: 'test-correlation-id',
            user: 1,
            to: '+1234567890',
            body: 'Test SMS',
        );
    }

    private function makeHandler(array $providers, ?LoggerInterface $logger = null, ?AuditLogger $auditLogger = null): SmsNotificationHandler
    {
        return new SmsNotificationHandler(
            $providers,
            $logger ?? $this->createMock(LoggerInterface::class),
            $auditLogger ?? $this->createMock(AuditLogger::class),
        );
    }

    public function testFirstProviderSucceeds(): void
    {
        $provider = $this->createMock(SmsProviderInterface::class);
        $provider->expects($this->once())->method('send');

        $this->makeHandler([$provider])($this->message);
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

        $this->makeHandler([$failing, $succeeding], $logger)($this->message);
    }

    public function testThrowsWhenAllProvidersFail(): void
    {
        $provider1 = $this->createMock(SmsProviderInterface::class);
        $provider1->method('send')->willThrowException(new ProviderException('fail'));

        $provider2 = $this->createMock(SmsProviderInterface::class);
        $provider2->method('send')->willThrowException(new ProviderException('fail'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('warning');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All SMS providers failed for recipient +1234567890');

        $this->makeHandler([$provider1, $provider2], $logger)($this->message);
    }

    public function testThrowsWhenNoProviders(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->makeHandler([])($this->message);
    }

    public function testSecondProviderNotCalledWhenFirstSucceeds(): void
    {
        $first = $this->createMock(SmsProviderInterface::class);
        $first->expects($this->once())->method('send');

        $second = $this->createMock(SmsProviderInterface::class);
        $second->expects($this->never())->method('send');

        $this->makeHandler([$first, $second])($this->message);
    }

    public function testAuditLogsProviderSuccess(): void
    {
        $provider = $this->createMock(SmsProviderInterface::class);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())
            ->method('logProviderSuccess')
            ->with('test-correlation-id', 'sms', get_class($provider), 1, '+1234567890');

        $this->makeHandler([$provider], auditLogger: $auditLogger)($this->message);
    }

    public function testAuditLogsProviderFailureThenSuccess(): void
    {
        $failing = $this->createMock(SmsProviderInterface::class);
        $failing->method('send')->willThrowException(new ProviderException('Provider 1 down'));

        $succeeding = $this->createMock(SmsProviderInterface::class);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())->method('logProviderFailure')
            ->with('test-correlation-id', 'sms', get_class($failing), 1, '+1234567890', 'Provider 1 down');
        $auditLogger->expects($this->once())->method('logProviderSuccess')
            ->with('test-correlation-id', 'sms', get_class($succeeding), 1, '+1234567890');

        $this->makeHandler([$failing, $succeeding], auditLogger: $auditLogger)($this->message);
    }

    public function testAuditLogsAllProvidersFailedBeforeException(): void
    {
        $provider = $this->createMock(SmsProviderInterface::class);
        $provider->method('send')->willThrowException(new ProviderException('fail'));

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())->method('logAllProvidersFailed')
            ->with('test-correlation-id', 'sms', 1, '+1234567890');

        try {
            $this->makeHandler([$provider], auditLogger: $auditLogger)($this->message);
        } catch (\RuntimeException) {
        }
    }
}
