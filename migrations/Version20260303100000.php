<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create e-commerce tables.
 */
final class Version20260303100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ecommerce_category_produit, ecommerce_produit, ecommerce_commande, ecommerce_ligne_commande, ecommerce_review';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ecommerce_category_produit (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ecommerce_produit (
            id INT AUTO_INCREMENT NOT NULL,
            category_id INT DEFAULT NULL,
            nom VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            prix INT NOT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            INDEX IDX_ecommerce_produit_category (category_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ecommerce_commande (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            montant_total INT NOT NULL,
            statut VARCHAR(50) NOT NULL,
            INDEX IDX_ecommerce_commande_user (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ecommerce_ligne_commande (
            id INT AUTO_INCREMENT NOT NULL,
            commande_id INT NOT NULL,
            produit_id INT NOT NULL,
            quantite INT NOT NULL,
            prix_unitaire INT NOT NULL,
            INDEX IDX_ecommerce_ligne_commande (commande_id),
            INDEX IDX_ecommerce_ligne_produit (produit_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ecommerce_review (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            produit_id INT NOT NULL,
            rating SMALLINT NOT NULL,
            comment LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            status VARCHAR(20) NOT NULL,
            INDEX IDX_ecommerce_review_user (user_id),
            INDEX IDX_ecommerce_review_produit (produit_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE ecommerce_produit ADD CONSTRAINT FK_ecommerce_produit_category FOREIGN KEY (category_id) REFERENCES ecommerce_category_produit (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ecommerce_commande ADD CONSTRAINT FK_ecommerce_commande_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_ligne_commande ADD CONSTRAINT FK_ecommerce_ligne_commande FOREIGN KEY (commande_id) REFERENCES ecommerce_commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_ligne_commande ADD CONSTRAINT FK_ecommerce_ligne_produit FOREIGN KEY (produit_id) REFERENCES ecommerce_produit (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_review ADD CONSTRAINT FK_ecommerce_review_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ecommerce_review ADD CONSTRAINT FK_ecommerce_review_produit FOREIGN KEY (produit_id) REFERENCES ecommerce_produit (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ecommerce_produit DROP FOREIGN KEY FK_ecommerce_produit_category');
        $this->addSql('ALTER TABLE ecommerce_commande DROP FOREIGN KEY FK_ecommerce_commande_user');
        $this->addSql('ALTER TABLE ecommerce_ligne_commande DROP FOREIGN KEY FK_ecommerce_ligne_commande');
        $this->addSql('ALTER TABLE ecommerce_ligne_commande DROP FOREIGN KEY FK_ecommerce_ligne_produit');
        $this->addSql('ALTER TABLE ecommerce_review DROP FOREIGN KEY FK_ecommerce_review_user');
        $this->addSql('ALTER TABLE ecommerce_review DROP FOREIGN KEY FK_ecommerce_review_produit');
        $this->addSql('DROP TABLE ecommerce_category_produit');
        $this->addSql('DROP TABLE ecommerce_produit');
        $this->addSql('DROP TABLE ecommerce_commande');
        $this->addSql('DROP TABLE ecommerce_ligne_commande');
        $this->addSql('DROP TABLE ecommerce_review');
    }
}
