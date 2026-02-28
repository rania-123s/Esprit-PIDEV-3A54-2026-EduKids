<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228155330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add likes and dislikes columns to cours table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cours ADD likes INT DEFAULT 0 NOT NULL, ADD dislikes INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cours DROP likes, DROP dislikes');
    }
}
