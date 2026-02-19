<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218202749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_cours_progress_cours DROP FOREIGN KEY FK_662674E87ECF78B0');
        $this->addSql('ALTER TABLE user_cours_progress_cours DROP FOREIGN KEY FK_662674E8F9735C7C');
        $this->addSql('ALTER TABLE user_cours_progress_user DROP FOREIGN KEY FK_5A91289A76ED395');
        $this->addSql('ALTER TABLE user_cours_progress_user DROP FOREIGN KEY FK_5A91289F9735C7C');
        $this->addSql('DROP TABLE user_cours_progress_cours');
        $this->addSql('DROP TABLE user_cours_progress_user');
        $this->addSql('ALTER TABLE user_cours_progress ADD user_id INT NOT NULL, ADD cours_id INT NOT NULL');
        $this->addSql('ALTER TABLE user_cours_progress ADD CONSTRAINT FK_8E1083C2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user_cours_progress ADD CONSTRAINT FK_8E1083C27ECF78B0 FOREIGN KEY (cours_id) REFERENCES cours (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E1083C2A76ED395 ON user_cours_progress (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8E1083C27ECF78B0 ON user_cours_progress (cours_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_cours_progress_cours (user_cours_progress_id INT NOT NULL, cours_id INT NOT NULL, INDEX IDX_662674E8F9735C7C (user_cours_progress_id), INDEX IDX_662674E87ECF78B0 (cours_id), PRIMARY KEY(user_cours_progress_id, cours_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_cours_progress_user (user_cours_progress_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_5A91289A76ED395 (user_id), INDEX IDX_5A91289F9735C7C (user_cours_progress_id), PRIMARY KEY(user_cours_progress_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_cours_progress_cours ADD CONSTRAINT FK_662674E87ECF78B0 FOREIGN KEY (cours_id) REFERENCES cours (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_cours_progress_cours ADD CONSTRAINT FK_662674E8F9735C7C FOREIGN KEY (user_cours_progress_id) REFERENCES user_cours_progress (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_cours_progress_user ADD CONSTRAINT FK_5A91289A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_cours_progress_user ADD CONSTRAINT FK_5A91289F9735C7C FOREIGN KEY (user_cours_progress_id) REFERENCES user_cours_progress (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_cours_progress DROP FOREIGN KEY FK_8E1083C2A76ED395');
        $this->addSql('ALTER TABLE user_cours_progress DROP FOREIGN KEY FK_8E1083C27ECF78B0');
        $this->addSql('DROP INDEX UNIQ_8E1083C2A76ED395 ON user_cours_progress');
        $this->addSql('DROP INDEX UNIQ_8E1083C27ECF78B0 ON user_cours_progress');
        $this->addSql('ALTER TABLE user_cours_progress DROP user_id, DROP cours_id');
    }
}
