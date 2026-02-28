<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message indexes for chat statistics queries';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('message');

        if (!$table->hasIndex('idx_message_created_at')) {
            $table->addIndex(['created_at'], 'idx_message_created_at');
        }

        if (!$table->hasIndex('idx_message_sender_created_at')) {
            $table->addIndex(['sender_id', 'created_at'], 'idx_message_sender_created_at');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('message');

        if ($table->hasIndex('idx_message_sender_created_at')) {
            $table->dropIndex('idx_message_sender_created_at');
        }

        if ($table->hasIndex('idx_message_created_at')) {
            $table->dropIndex('idx_message_created_at');
        }
    }
}

