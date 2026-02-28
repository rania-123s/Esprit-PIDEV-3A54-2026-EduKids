<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create message_attachment_summary table with unique attachment/user summary';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message_attachment_summary (
            id INT AUTO_INCREMENT NOT NULL,
            attachment_id INT NOT NULL,
            user_id INT NOT NULL,
            summary_text LONGTEXT NOT NULL,
            status VARCHAR(16) DEFAULT \'pending\' NOT NULL,
            error_message VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_mas_attachment (attachment_id),
            INDEX idx_mas_user (user_id),
            INDEX idx_mas_status (status),
            INDEX idx_mas_created_at (created_at),
            UNIQUE INDEX uniq_mas_attachment_user (attachment_id, user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE message_attachment_summary ADD CONSTRAINT FK_MAS_ATTACHMENT FOREIGN KEY (attachment_id) REFERENCES message_attachment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_attachment_summary ADD CONSTRAINT FK_MAS_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_attachment_summary DROP FOREIGN KEY FK_MAS_ATTACHMENT');
        $this->addSql('ALTER TABLE message_attachment_summary DROP FOREIGN KEY FK_MAS_USER');
        $this->addSql('DROP TABLE message_attachment_summary');
    }
}
