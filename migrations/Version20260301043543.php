<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing message columns (deleted_at, updated_at, etc.) when the table
 * was not fully migrated (e.g. still has date_envoi but missing soft-delete).
 */
final class Version20260301043543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at and updated_at to message table if missing';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('message');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        if (!\in_array('deleted_at', $names, true)) {
            $this->addSql('ALTER TABLE message ADD deleted_at DATETIME DEFAULT NULL');
        }
        if (!\in_array('updated_at', $names, true)) {
            $this->addSql('ALTER TABLE message ADD updated_at DATETIME DEFAULT NULL');
        }
        if (!\in_array('type', $names, true)) {
            $this->addSql("ALTER TABLE message ADD type VARCHAR(20) NOT NULL DEFAULT 'text'");
        }
        if (!\in_array('status', $names, true)) {
            $this->addSql("ALTER TABLE message ADD status VARCHAR(20) NOT NULL DEFAULT 'sent'");
        }
        if (!\in_array('file_path', $names, true)) {
            $this->addSql('ALTER TABLE message ADD file_path VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();
        $columns = $sm->listTableColumns('message');
        $names = array_map(static fn ($c) => strtolower($c->getName()), $columns);

        $drops = [];
        if (\in_array('deleted_at', $names, true)) {
            $drops[] = 'DROP deleted_at';
        }
        if (\in_array('updated_at', $names, true)) {
            $drops[] = 'DROP updated_at';
        }
        if (\in_array('type', $names, true)) {
            $drops[] = 'DROP type';
        }
        if (\in_array('status', $names, true)) {
            $drops[] = 'DROP status';
        }
        if (\in_array('file_path', $names, true)) {
            $drops[] = 'DROP file_path';
        }
        if ($drops !== []) {
            $this->addSql('ALTER TABLE message ' . implode(', ', $drops));
        }
    }
}
