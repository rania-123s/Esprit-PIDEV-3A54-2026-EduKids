<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add conversation.last_auto_reply_at for chat auto-reply cooldown';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation ADD last_auto_reply_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_conversation_last_auto_reply_at ON conversation (last_auto_reply_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_conversation_last_auto_reply_at ON conversation');
        $this->addSql('ALTER TABLE conversation DROP last_auto_reply_at');
    }
}

