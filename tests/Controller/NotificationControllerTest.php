<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Message\EmailNotificationMessage;
use App\Message\SmsNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class NotificationControllerTest extends WebTestCase
{
    public static function setUpBeforeClass(): void
    {
        static::bootKernel();
        $dbPath = static::getContainer()->getParameter('kernel.project_dir').'/var/test.db';
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());
        static::ensureKernelShutdown();
    }

    // --- 202 success cases ---

    public function testEmailNotificationReturns202(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 1,
            'channels' => ['email'],
            'data' => [
                'email' => ['to' => 'user@example.com', 'subject' => 'Hello', 'body' => 'Test'],
            ],
        ]));

        $this->assertSame(202, $client->getResponse()->getStatusCode());
    }

    public function testEmailNotificationQueuesMessage(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 1,
            'channels' => ['email'],
            'data' => [
                'email' => ['to' => 'user@example.com', 'subject' => 'Hello', 'body' => 'Test'],
            ],
        ]));

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.notifications_email');
        $sent = $transport->getSent();

        $this->assertCount(1, $sent);
        $this->assertInstanceOf(EmailNotificationMessage::class, $sent[0]->getMessage());

        $msg = $sent[0]->getMessage();
        $this->assertSame(1, $msg->user);
        $this->assertSame('user@example.com', $msg->to);
        $this->assertSame('Hello', $msg->subject);
        $this->assertSame('Test', $msg->body);
    }

    public function testSmsNotificationReturns202(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 2,
            'channels' => ['sms'],
            'data' => [
                'sms' => ['to' => '+1234567890', 'body' => 'Hello'],
            ],
        ]));

        $this->assertSame(202, $client->getResponse()->getStatusCode());
    }

    public function testSmsNotificationQueuesMessage(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 2,
            'channels' => ['sms'],
            'data' => [
                'sms' => ['to' => '+1234567890', 'body' => 'Hello'],
            ],
        ]));

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.notifications_sms');
        $sent = $transport->getSent();

        $this->assertCount(1, $sent);
        $this->assertInstanceOf(SmsNotificationMessage::class, $sent[0]->getMessage());

        $msg = $sent[0]->getMessage();
        $this->assertSame(2, $msg->user);
        $this->assertSame('+1234567890', $msg->to);
        $this->assertSame('Hello', $msg->body);
    }

    public function testBothChannelsQueueTwoMessages(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 3,
            'channels' => ['email', 'sms'],
            'data' => [
                'email' => ['to' => 'user@example.com', 'subject' => 'Hi', 'body' => 'Body'],
                'sms' => ['to' => '+1234567890', 'body' => 'Hi'],
            ],
        ]));

        $this->assertSame(202, $client->getResponse()->getStatusCode());

        $emailTransport = static::getContainer()->get('messenger.transport.notifications_email');
        $smsTransport = static::getContainer()->get('messenger.transport.notifications_sms');

        $this->assertCount(1, $emailTransport->getSent());
        $this->assertCount(1, $smsTransport->getSent());
    }

    // --- 422 validation failure cases ---

    public function testMissingChannelDataReturns422(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 1,
            'channels' => ['email'],
            'data' => [],
        ]));

        $this->assertSame(422, $client->getResponse()->getStatusCode());

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('dataValid', $body['errors']);
    }

    public function testEmptyChannelsArrayReturns422(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 1,
            'channels' => [],
            'data' => [],
        ]));

        $this->assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function testMissingChannelsReturns422(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 1,
            'data' => [],
        ]));

        $this->assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function testDisabledChannelReturns422(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 1,
            'channels' => ['push'],
            'data' => ['push' => ['token' => 'abc', 'body' => 'Hi']],
        ]));

        $this->assertSame(422, $client->getResponse()->getStatusCode());

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('channelsValid', $body['errors']);
    }

    public function testInvalidJsonReturns422(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], 'not json');

        $this->assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function testNothingQueuedOnValidationFailure(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notifications', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'user' => 1,
            'channels' => ['email'],
            'data' => [],
        ]));

        $emailTransport = static::getContainer()->get('messenger.transport.notifications_email');
        $smsTransport = static::getContainer()->get('messenger.transport.notifications_sms');

        $this->assertCount(0, $emailTransport->getSent());
        $this->assertCount(0, $smsTransport->getSent());
    }
}
