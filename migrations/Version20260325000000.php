<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification_audit table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_audit (
            id            BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            correlation_id VARCHAR(36)  NOT NULL,
            event_type    VARCHAR(50)  NOT NULL,
            channel       VARCHAR(20)  DEFAULT NULL,
            provider      VARCHAR(255) DEFAULT NULL,
            user_id       INT UNSIGNED NOT NULL,
            recipient     VARCHAR(255) DEFAULT NULL,
            error         LONGTEXT     DEFAULT NULL,
            created_at    DATETIME     NOT NULL,
            INDEX idx_correlation (correlation_id),
            INDEX idx_user        (user_id),
            INDEX idx_created_at  (created_at),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_audit');
    }
}
