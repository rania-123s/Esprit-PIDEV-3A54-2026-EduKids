<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create quiz tables: quiz_quiz, quiz_question, quiz_question_option, quiz_attempt.
 */
final class Version20260302120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create quiz_quiz, quiz_question, quiz_question_option, quiz_attempt tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quiz_quiz (
            id INT AUTO_INCREMENT NOT NULL,
            titre VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            published TINYINT(1) DEFAULT 0 NOT NULL,
            chatbot_enabled TINYINT(1) DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz_question (
            id INT AUTO_INCREMENT NOT NULL,
            quiz_id INT NOT NULL,
            texte LONGTEXT NOT NULL,
            type VARCHAR(20) NOT NULL,
            bonne_reponse LONGTEXT DEFAULT NULL,
            ordre INT DEFAULT 0 NOT NULL,
            INDEX IDX_quiz_question_quiz (quiz_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz_question_option (
            id INT AUTO_INCREMENT NOT NULL,
            question_id INT NOT NULL,
            texte VARCHAR(1000) NOT NULL,
            ordre INT DEFAULT 0 NOT NULL,
            `correct` TINYINT(1) DEFAULT 0 NOT NULL,
            INDEX IDX_quiz_question_option_question (question_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE quiz_attempt (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            quiz_id INT NOT NULL,
            score INT NOT NULL,
            total_questions INT NOT NULL,
            answers JSON DEFAULT NULL,
            completed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_quiz_attempt_user (user_id),
            INDEX IDX_quiz_attempt_quiz (quiz_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE quiz_question ADD CONSTRAINT FK_quiz_question_quiz FOREIGN KEY (quiz_id) REFERENCES quiz_quiz (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_question_option ADD CONSTRAINT FK_quiz_question_option_question FOREIGN KEY (question_id) REFERENCES quiz_question (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_quiz_attempt_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz_attempt ADD CONSTRAINT FK_quiz_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES quiz_quiz (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quiz_question DROP FOREIGN KEY FK_quiz_question_quiz');
        $this->addSql('ALTER TABLE quiz_question_option DROP FOREIGN KEY FK_quiz_question_option_question');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_quiz_attempt_user');
        $this->addSql('ALTER TABLE quiz_attempt DROP FOREIGN KEY FK_quiz_attempt_quiz');
        $this->addSql('DROP TABLE quiz_quiz');
        $this->addSql('DROP TABLE quiz_question');
        $this->addSql('DROP TABLE quiz_question_option');
        $this->addSql('DROP TABLE quiz_attempt');
    }
}
