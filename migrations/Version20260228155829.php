<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228155829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add video_url, youtube_url, image columns to lecon table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lecon ADD video_url VARCHAR(255) DEFAULT NULL, ADD youtube_url VARCHAR(255) DEFAULT NULL, ADD image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lecon DROP video_url, DROP youtube_url, DROP image');
    }
}
