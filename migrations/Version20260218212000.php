<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218212000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple courses per user in user_cours_progress with unique pair (user_id, cours_id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8E1083C2A76ED395 ON user_cours_progress');
        $this->addSql('DROP INDEX UNIQ_8E1083C27ECF78B0 ON user_cours_progress');
        $this->addSql('CREATE INDEX IDX_8E1083C2A76ED395 ON user_cours_progress (user_id)');
        $this->addSql('CREATE INDEX IDX_8E1083C27ECF78B0 ON user_cours_progress (cours_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_COURS_PROGRESS_USER_COURS ON user_cours_progress (user_id, cours_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_COURS_PROGRESS_USER_COURS ON user_cours_progress');
        $this->addSql('DROP INDEX IDX_8E1083C2A76ED395 ON user_cours_progress');
        $this->addSql('DROP INDEX IDX_8E1083C27ECF78B0 ON user_cours_progress');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E1083C2A76ED395 ON user_cours_progress (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E1083C27ECF78B0 ON user_cours_progress (cours_id)');
    }
}
