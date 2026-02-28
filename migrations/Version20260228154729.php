<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228154729 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_cours_progress table for UserCoursProgress entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_cours_progress (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, cours_id INT NOT NULL, progress INT DEFAULT 0 NOT NULL, INDEX IDX_8E1083C2A76ED395 (user_id), INDEX IDX_8E1083C27ECF78B0 (cours_id), UNIQUE INDEX UNIQ_USER_COURS_PROGRESS_USER_COURS (user_id, cours_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_cours_progress ADD CONSTRAINT FK_8E1083C2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_cours_progress ADD CONSTRAINT FK_8E1083C27ECF78B0 FOREIGN KEY (cours_id) REFERENCES cours (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_cours_progress DROP FOREIGN KEY FK_8E1083C2A76ED395');
        $this->addSql('ALTER TABLE user_cours_progress DROP FOREIGN KEY FK_8E1083C27ECF78B0');
        $this->addSql('DROP TABLE user_cours_progress');
    }
}
