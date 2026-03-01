<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ensure conversation table uses InnoDB (fixes MySQL 1824 when other tables reference it).
 */
final class Version20260306130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure conversation table is InnoDB';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $tables = $sm->listTableNames();
        if (!in_array('conversation', $tables, true)) {
            return;
        }

        // Force InnoDB so FKs from other tables can reference conversation
        $this->addSql('ALTER TABLE conversation ENGINE=InnoDB');
    }

    public function down(Schema $schema): void
    {
        // no-op
    }
}
