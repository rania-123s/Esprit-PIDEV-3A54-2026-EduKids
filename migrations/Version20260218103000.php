<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add type and duration columns on message_attachment for voice messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message_attachment ADD type VARCHAR(20) NOT NULL DEFAULT \'file\', ADD duration INT DEFAULT NULL');
        $this->addSql('UPDATE message_attachment SET type = CASE WHEN is_image = 1 THEN \'image\' ELSE \'file\' END');
        $this->addSql('CREATE INDEX idx_message_attachment_type ON message_attachment (type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_message_attachment_type ON message_attachment');
        $this->addSql('ALTER TABLE message_attachment DROP type, DROP duration');
    }
}
