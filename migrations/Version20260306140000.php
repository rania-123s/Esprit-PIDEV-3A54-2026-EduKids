<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Doctrine convention: rename id_evenement to evenement_id (FK _id suffix).
 */
final class Version20260306140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename id_evenement to evenement_id in reservation and ressource (Doctrine FK convention)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849558B13D439');
        $this->addSql('DROP INDEX IDX_42C849558B13D439 ON reservation');
        $this->addSql('ALTER TABLE reservation CHANGE id_evenement evenement_id INT NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement)');
        $this->addSql('CREATE INDEX IDX_42C84955FD02F13 ON reservation (evenement_id)');

        $this->addSql('ALTER TABLE ressource DROP FOREIGN KEY FK_939F45448B13D439');
        $this->addSql('DROP INDEX IDX_939F45448B13D439 ON ressource');
        $this->addSql('ALTER TABLE ressource CHANGE id_evenement evenement_id INT NOT NULL');
        $this->addSql('ALTER TABLE ressource ADD CONSTRAINT FK_939F4544FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id_evenement)');
        $this->addSql('CREATE INDEX IDX_939F4544FD02F13 ON ressource (evenement_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955FD02F13');
        $this->addSql('DROP INDEX IDX_42C84955FD02F13 ON reservation');
        $this->addSql('ALTER TABLE reservation CHANGE evenement_id id_evenement INT NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849558B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('CREATE INDEX IDX_42C849558B13D439 ON reservation (id_evenement)');

        $this->addSql('ALTER TABLE ressource DROP FOREIGN KEY FK_939F4544FD02F13');
        $this->addSql('DROP INDEX IDX_939F4544FD02F13 ON ressource');
        $this->addSql('ALTER TABLE ressource CHANGE evenement_id id_evenement INT NOT NULL');
        $this->addSql('ALTER TABLE ressource ADD CONSTRAINT FK_939F45448B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('CREATE INDEX IDX_939F45448B13D439 ON ressource (id_evenement)');
    }
}
