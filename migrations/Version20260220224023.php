<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220224023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(255) DEFAULT NULL, nb_adultes INT DEFAULT 0 NOT NULL, nb_enfants INT DEFAULT 0 NOT NULL, date_reservation DATETIME NOT NULL, user_id INT NOT NULL, id_evenement INT NOT NULL, INDEX IDX_42C84955A76ED395 (user_id), INDEX IDX_42C849558B13D439 (id_evenement), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849558B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('ALTER TABLE evenement ADD nb_places_disponibles INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849558B13D439');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('ALTER TABLE evenement DROP nb_places_disponibles');
    }
}
