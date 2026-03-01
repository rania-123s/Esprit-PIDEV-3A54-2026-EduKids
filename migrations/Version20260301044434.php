<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename message.expediteur_id to message.sender_id when missing.
 */
final class Version20260301044434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add or rename message column to sender_id for compatibility with Message entity';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('message');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        $hasSenderId = \in_array('sender_id', $names, true);
        $hasExpediteurId = \in_array('expediteur_id', $names, true);

        if ($hasSenderId) {
            return;
        }

        if ($hasExpediteurId) {
            $foreignKeys = $sm->listTableForeignKeys('message');
            foreach ($foreignKeys as $fk) {
                if (\in_array('expediteur_id', $fk->getLocalColumns(), true)) {
                    $this->addSql('ALTER TABLE message DROP FOREIGN KEY ' . $fk->getQuotedName($this->connection->getDatabasePlatform()));
                    break;
                }
            }
            $indexes = $sm->listTableIndexes('message');
            foreach ($indexes as $idx) {
                if (\in_array('expediteur_id', $idx->getColumns(), true)) {
                    $idxName = $idx->getQuotedName($this->connection->getDatabasePlatform());
                    $this->addSql('DROP INDEX ' . $idxName . ' ON message');
                    break;
                }
            }
            $this->addSql('ALTER TABLE message CHANGE expediteur_id sender_id INT NOT NULL');
            $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_MESSAGE_SENDER FOREIGN KEY (sender_id) REFERENCES `user` (id)');
            $this->addSql('CREATE INDEX IDX_MESSAGE_SENDER ON message (sender_id)');
            return;
        }

        $this->addSql('ALTER TABLE message ADD sender_id INT NOT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_MESSAGE_SENDER FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_MESSAGE_SENDER ON message (sender_id)');
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('message');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        if (!\in_array('sender_id', $names, true)) {
            return;
        }

        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_MESSAGE_SENDER');
        $this->addSql('DROP INDEX IDX_MESSAGE_SENDER ON message');
        $this->addSql('ALTER TABLE message CHANGE sender_id expediteur_id INT NOT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F10335F61 FOREIGN KEY (expediteur_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_B6BD307F10335F61 ON message (expediteur_id)');
    }
}
