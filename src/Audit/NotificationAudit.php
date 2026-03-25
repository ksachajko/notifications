<?php

declare(strict_types=1);

namespace App\Audit;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_audit')]
#[ORM\Index(columns: ['correlation_id'], name: 'idx_correlation')]
#[ORM\Index(columns: ['user_id'], name: 'idx_user')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
class NotificationAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $correlationId;

    #[ORM\Column(length: 50)]
    private string $eventType;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $channel;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $provider;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $userId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recipient;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $correlationId,
        string $eventType,
        int $userId,
        ?string $channel = null,
        ?string $provider = null,
        ?string $recipient = null,
        ?string $error = null,
    ) {
        $this->correlationId = $correlationId;
        $this->eventType = $eventType;
        $this->userId = $userId;
        $this->channel = $channel;
        $this->provider = $provider;
        $this->recipient = $recipient;
        $this->error = $error;
        $this->createdAt = new \DateTimeImmutable();
    }
}
