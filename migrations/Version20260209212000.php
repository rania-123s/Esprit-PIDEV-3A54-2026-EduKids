<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209212000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add conversation participants and message metadata for chat';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation_participant (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, user_id INT NOT NULL, deleted_at DATETIME DEFAULT NULL, last_read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_conversation_user (conversation_id, user_id), INDEX IDX_8A1F2E9D9AC0396 (conversation_id), INDEX IDX_8A1F2E9DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_8A1F2E9D9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_8A1F2E9DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO conversation_participant (conversation_id, user_id, created_at) SELECT id, admin_id, NOW() FROM conversation');
        $this->addSql('INSERT INTO conversation_participant (conversation_id, user_id, created_at) SELECT id, parent_id, NOW() FROM conversation');
        $this->addSql('ALTER TABLE message ADD type VARCHAR(20) NOT NULL DEFAULT \'text\', ADD file_path VARCHAR(255) DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_8A1F2E9D9AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_8A1F2E9DA76ED395');
        $this->addSql('DROP TABLE conversation_participant');
        $this->addSql('ALTER TABLE message DROP type, DROP file_path, DROP updated_at, DROP deleted_at');
    }
}
