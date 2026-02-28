<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hidden_at on conversation_participant for conversation hide-per-user.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_participant ADD hidden_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cp_hidden_at ON conversation_participant (hidden_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_cp_hidden_at ON conversation_participant');
        $this->addSql('ALTER TABLE conversation_participant DROP hidden_at');
    }
}

