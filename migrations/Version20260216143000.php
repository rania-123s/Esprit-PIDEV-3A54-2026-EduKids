<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260216143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refactor chat schema for private/group conversations, participant roles, and message status';
    }

    public function up(Schema $schema): void
    {
        // 1) Conversation: add fields used by the new chat model.
        $this->addSql('ALTER TABLE conversation ADD title VARCHAR(120) DEFAULT NULL, ADD is_group TINYINT(1) DEFAULT 0 NOT NULL, ADD private_key VARCHAR(80) DEFAULT NULL');

        // 2) Participant table for private/group membership.
        $this->addSql('CREATE TABLE IF NOT EXISTS conversation_participant (
            id INT AUTO_INCREMENT NOT NULL,
            conversation_id INT NOT NULL,
            user_id INT NOT NULL,
            role VARCHAR(16) DEFAULT \'member\' NOT NULL,
            deleted_at DATETIME DEFAULT NULL,
            last_read_at DATETIME DEFAULT NULL,
            joined_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_conversation_user (conversation_id, user_id),
            INDEX idx_cp_conversation (conversation_id),
            INDEX idx_cp_user (user_id),
            INDEX idx_cp_deleted_at (deleted_at),
            INDEX idx_cp_role (role),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_CP_CONVERSATION FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_participant ADD CONSTRAINT FK_CP_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // Backfill participants from legacy conversation(admin_id,parent_id).
        $this->addSql('INSERT IGNORE INTO conversation_participant (conversation_id, user_id, role, joined_at)
            SELECT id, admin_id, \'admin\', NOW() FROM conversation');
        $this->addSql('INSERT IGNORE INTO conversation_participant (conversation_id, user_id, role, joined_at)
            SELECT id, parent_id, \'member\', NOW() FROM conversation');

        // 3) Message migration from legacy chat schema to new conversation schema.
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F1A9A7125');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F10335F61');
        $this->addSql('DROP INDEX IDX_B6BD307F1A9A7125 ON message');
        $this->addSql('DROP INDEX IDX_B6BD307F10335F61 ON message');

        $this->addSql('ALTER TABLE message
            CHANGE expediteur_id sender_id INT NOT NULL,
            CHANGE contenu content LONGTEXT NOT NULL,
            CHANGE date_envoi created_at DATETIME NOT NULL,
            CHANGE lu is_read TINYINT(1) NOT NULL,
            CHANGE chat_id conversation_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE message
            ADD type VARCHAR(20) NOT NULL DEFAULT \'text\',
            ADD file_path VARCHAR(255) DEFAULT NULL,
            ADD updated_at DATETIME DEFAULT NULL,
            ADD deleted_at DATETIME DEFAULT NULL,
            ADD status VARCHAR(20) NOT NULL DEFAULT \'sent\'');

        // Map old chat_id -> conversation_id through parent_id.
        $this->addSql('UPDATE message m
            INNER JOIN chat ch ON ch.id = m.conversation_id
            INNER JOIN conversation c ON c.parent_id = ch.parent_id
            SET m.conversation_id = c.id
            WHERE m.conversation_id IS NOT NULL');

        // Drop orphan rows that cannot be mapped safely.
        $this->addSql('DELETE FROM message WHERE conversation_id IS NULL');

        $this->addSql('ALTER TABLE message MODIFY conversation_id INT NOT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_MESSAGE_CONVERSATION FOREIGN KEY (conversation_id) REFERENCES conversation (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_MESSAGE_SENDER FOREIGN KEY (sender_id) REFERENCES `user` (id)');
        $this->addSql('CREATE INDEX IDX_MESSAGE_CONVERSATION ON message (conversation_id)');
        $this->addSql('CREATE INDEX IDX_MESSAGE_SENDER ON message (sender_id)');
        $this->addSql('CREATE INDEX idx_message_conversation_created_at ON message (conversation_id, created_at)');
        $this->addSql('CREATE INDEX idx_message_status ON message (status)');

        // Legacy table no longer needed after message mapping.
        $this->addSql('DROP TABLE chat');

        // 4) Remove legacy conversation admin/parent fields now replaced by participants.
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9642B8210');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9727ACA70');
        $this->addSql('DROP INDEX IDX_8A8E26E9642B8210 ON conversation');
        $this->addSql('DROP INDEX IDX_8A8E26E9727ACA70 ON conversation');
        $this->addSql('ALTER TABLE conversation DROP admin_id, DROP parent_id');

        $this->addSql('CREATE UNIQUE INDEX uniq_conversation_private_key ON conversation (private_key)');
        $this->addSql('CREATE INDEX idx_conversation_updated_at ON conversation (updated_at)');
        $this->addSql('CREATE INDEX idx_conversation_is_group ON conversation (is_group)');
    }

    public function down(Schema $schema): void
    {
        // Down kept intentionally minimal: this migration is designed to repair legacy chat schema drift.
        $this->abortIf(true, 'Irreversible migration.');
    }
}
