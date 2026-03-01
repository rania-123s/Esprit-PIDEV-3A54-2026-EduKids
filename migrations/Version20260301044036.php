<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename message.date_envoi to message.created_at.
 */
final class Version20260301044036 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename message.date_envoi to message.created_at';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('message');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        if (\in_array('date_envoi', $names, true) && !\in_array('created_at', $names, true)) {
            $this->addSql('ALTER TABLE message CHANGE date_envoi created_at DATETIME NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('message');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        if (\in_array('created_at', $names, true)) {
            $this->addSql('ALTER TABLE message CHANGE created_at date_envoi DATETIME NOT NULL');
        }
    }
}
