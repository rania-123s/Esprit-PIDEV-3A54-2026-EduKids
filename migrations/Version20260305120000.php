<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ensure message table has content column (fix schema if still using contenu).
 * Run this if app:chat:load-fixtures fails with "Column 'content' not found".
 */
final class Version20260305120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure message table has content column for chat entity compatibility';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('message');
        $columnNames = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        // If content exists, we're fine
        if (in_array('content', $columnNames, true)) {
            return;
        }

        // Old schema has contenu, lu, chat_id, expediteur_id, date_envoi
        if (in_array('contenu', $columnNames, true)) {
            $this->addSql('ALTER TABLE message ADD content LONGTEXT DEFAULT NULL');
            $this->addSql('UPDATE message SET content = COALESCE(contenu, "")');
            $this->addSql('ALTER TABLE message MODIFY content LONGTEXT NOT NULL');
            $this->addSql('ALTER TABLE message DROP contenu');
        }

        if (!in_array('is_read', $columnNames, true) && in_array('lu', $columnNames, true)) {
            $this->addSql('ALTER TABLE message CHANGE lu is_read TINYINT(1) NOT NULL');
        }

        if (!in_array('conversation_id', $columnNames, true)) {
            if (in_array('chat_id', $columnNames, true)) {
                $fks = $sm->listTableForeignKeys('message');
                foreach ($fks as $fk) {
                    if (in_array('chat_id', $fk->getLocalColumns(), true)) {
                        $this->addSql('ALTER TABLE message DROP FOREIGN KEY ' . $fk->getQuotedName($this->connection->getDatabasePlatform()));
                        break;
                    }
                }
                $this->addSql('ALTER TABLE message CHANGE chat_id conversation_id INT DEFAULT NULL');
            } else {
                $this->addSql('ALTER TABLE message ADD conversation_id INT DEFAULT NULL');
            }
        }

        if (!in_array('sender_id', $columnNames, true) && in_array('expediteur_id', $columnNames, true)) {
            $this->addSql('ALTER TABLE message CHANGE expediteur_id sender_id INT NOT NULL');
        }

        if (!in_array('created_at', $columnNames, true) && in_array('date_envoi', $columnNames, true)) {
            $this->addSql('ALTER TABLE message CHANGE date_envoi created_at DATETIME NOT NULL');
        }

        $columns = $sm->listTableColumns('message');
        $columnNames = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        if (!in_array('type', $columnNames, true)) {
            $this->addSql("ALTER TABLE message ADD type VARCHAR(20) NOT NULL DEFAULT 'text'");
        }
        if (!in_array('status', $columnNames, true)) {
            $this->addSql("ALTER TABLE message ADD status VARCHAR(20) NOT NULL DEFAULT 'sent'");
        }
        if (!in_array('updated_at', $columnNames, true)) {
            $this->addSql('ALTER TABLE message ADD updated_at DATETIME DEFAULT NULL');
        }
        if (!in_array('deleted_at', $columnNames, true)) {
            $this->addSql('ALTER TABLE message ADD deleted_at DATETIME DEFAULT NULL');
        }
        if (!in_array('file_path', $columnNames, true)) {
            $this->addSql('ALTER TABLE message ADD file_path VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        // No down - this is a fix migration
    }
}
