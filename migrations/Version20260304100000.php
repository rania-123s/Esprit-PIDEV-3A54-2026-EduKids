<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing conversation columns (title, is_group, private_key, etc.) if absent.
 */
final class Version20260304100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add title, is_group, private_key to conversation table if missing';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('conversation');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        if (!\in_array('title', $names, true)) {
            $this->addSql('ALTER TABLE conversation ADD title VARCHAR(120) DEFAULT NULL');
        }
        if (!\in_array('is_group', $names, true)) {
            $this->addSql('ALTER TABLE conversation ADD is_group TINYINT(1) DEFAULT 0 NOT NULL');
        }
        if (!\in_array('private_key', $names, true)) {
            $this->addSql('ALTER TABLE conversation ADD private_key VARCHAR(80) DEFAULT NULL');
        }
        if (!\in_array('last_auto_reply_at', $names, true)) {
            $this->addSql('ALTER TABLE conversation ADD last_auto_reply_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('conversation');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        $drops = [];
        if (\in_array('title', $names, true)) {
            $drops[] = 'DROP title';
        }
        if (\in_array('is_group', $names, true)) {
            $drops[] = 'DROP is_group';
        }
        if (\in_array('private_key', $names, true)) {
            $drops[] = 'DROP private_key';
        }
        if (\in_array('last_auto_reply_at', $names, true)) {
            $drops[] = 'DROP last_auto_reply_at';
        }
        if ($drops !== []) {
            $this->addSql('ALTER TABLE conversation ' . implode(', ', $drops));
        }
    }
}
