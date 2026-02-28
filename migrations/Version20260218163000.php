<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification table for chat message notifications';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (
            id INT AUTO_INCREMENT NOT NULL,
            receiver_id INT NOT NULL,
            sender_id INT NOT NULL,
            type VARCHAR(30) DEFAULT \'message\' NOT NULL,
            conversation_id INT NOT NULL,
            text VARCHAR(255) NOT NULL,
            is_read TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_notification_receiver_is_read_created (receiver_id, is_read, created_at),
            INDEX idx_notification_created_at (created_at),
            INDEX idx_notification_conversation (conversation_id),
            INDEX idx_notification_sender (sender_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_RECEIVER FOREIGN KEY (receiver_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_NOTIFICATION_SENDER FOREIGN KEY (sender_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_RECEIVER');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_NOTIFICATION_SENDER');
        $this->addSql('DROP TABLE notification');
    }
}
