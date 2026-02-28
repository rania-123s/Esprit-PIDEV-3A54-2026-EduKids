<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260216122814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation ADD title VARCHAR(120) DEFAULT NULL, ADD is_group TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_8A1F2E9DA76ED395');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_8A1F2E9D9AC0396');
        $this->addSql('DROP INDEX idx_8a1f2e9d9ac0396 ON conversation_participant');
        $this->addSql('CREATE INDEX IDX_398016619AC0396 ON conversation_participant (conversation_id)');
        $this->addSql('DROP INDEX idx_8a1f2e9da76ed395 ON conversation_participant');
        $this->addSql('CREATE INDEX IDX_39801661A76ED395 ON conversation_participant (user_id)');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_8A1F2E9DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_8A1F2E9D9AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP title, DROP is_group');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_398016619AC0396');
        $this->addSql('ALTER TABLE conversation_participant DROP FOREIGN KEY FK_39801661A76ED395');
        $this->addSql('DROP INDEX idx_39801661a76ed395 ON conversation_participant');
        $this->addSql('CREATE INDEX IDX_8A1F2E9DA76ED395 ON conversation_participant (user_id)');
        $this->addSql('DROP INDEX idx_398016619ac0396 ON conversation_participant');
        $this->addSql('CREATE INDEX IDX_8A1F2E9D9AC0396 ON conversation_participant (conversation_id)');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_398016619AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_39801661A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }
}
