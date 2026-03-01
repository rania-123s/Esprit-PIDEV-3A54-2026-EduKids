<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create conversation table if missing (fixes MySQL 1824 "Failed to open the referenced table").
 */
final class Version20260306120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create conversation table if missing';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = $sm->listTableNames();
        if (in_array('conversation', $tables, true)) {
            return;
        }

        $this->addSql('CREATE TABLE conversation (
            id INT AUTO_INCREMENT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            title VARCHAR(120) DEFAULT NULL,
            is_group TINYINT(1) DEFAULT 0 NOT NULL,
            private_key VARCHAR(80) DEFAULT NULL,
            last_auto_reply_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id),
            UNIQUE INDEX uniq_conversation_private_key (private_key),
            INDEX idx_conversation_updated_at (updated_at),
            INDEX idx_conversation_is_group (is_group)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS conversation');
    }
}
