<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216174500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message_attachment table for multi-file chat messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_attachment (
            id INT AUTO_INCREMENT NOT NULL,
            message_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(128) NOT NULL,
            storage_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            size BIGINT NOT NULL,
            is_image TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_message_attachment_message (message_id),
            INDEX idx_message_attachment_created_at (created_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE message_attachment ADD CONSTRAINT FK_MESSAGE_ATTACHMENT_MESSAGE FOREIGN KEY (message_id) REFERENCES message (id) ON DELETE CASCADE');

        // Backfill legacy single-file messages.
        $this->addSql('INSERT INTO message_attachment (message_id, original_name, stored_name, storage_path, mime_type, size, is_image, created_at)
            SELECT
                m.id,
                SUBSTRING_INDEX(m.file_path, \'/\', -1),
                SUBSTRING_INDEX(m.file_path, \'/\', -1),
                m.file_path,
                CASE WHEN m.type = \'image\' THEN \'image/jpeg\' ELSE \'application/octet-stream\' END,
                0,
                CASE WHEN m.type = \'image\' THEN 1 ELSE 0 END,
                COALESCE(m.created_at, NOW())
            FROM message m
            WHERE m.file_path IS NOT NULL AND m.file_path <> \'\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_attachment DROP FOREIGN KEY FK_MESSAGE_ATTACHMENT_MESSAGE');
        $this->addSql('DROP TABLE message_attachment');
    }
}

