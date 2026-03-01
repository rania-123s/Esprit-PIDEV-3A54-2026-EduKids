<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create conversation_participant table if missing.
 */
final class Version20260304110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create conversation_participant table';
    }

    public function up(Schema $schema): void
    {
        // Création sans FK : erreur 1824 possible si table conversation en MyISAM ou format différent
        $this->addSql('CREATE TABLE IF NOT EXISTS conversation_participant (
            id INT AUTO_INCREMENT NOT NULL,
            conversation_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(16) DEFAULT \'member\' NOT NULL,
            deleted_at DATETIME DEFAULT NULL,
            hidden_at DATETIME DEFAULT NULL,
            last_read_at DATETIME DEFAULT NULL,
            joined_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_conversation_user (conversation_id, user_id),
            INDEX idx_cp_conversation (conversation_id),
            INDEX idx_cp_user (user_id),
            INDEX idx_cp_deleted_at (deleted_at),
            INDEX idx_cp_hidden_at (hidden_at),
            INDEX idx_cp_role (role),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE conversation_participant');
    }
}
