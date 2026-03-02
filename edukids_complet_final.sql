-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 17 fév. 2026 à 23:50
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `edukids`
--

-- Désactiver temporairement les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------

--
-- Structure de la table `chat`
--

CREATE TABLE IF NOT EXISTS `chat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `date_creation` datetime NOT NULL,
  `dernier_message` varchar(255) NOT NULL,
  `date_dernier_message` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

CREATE TABLE IF NOT EXISTS `commande` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `montant_total` int(11) NOT NULL,
  `statut` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_6EEAA67DA76ED395` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cours`
--

CREATE TABLE IF NOT EXISTS `cours` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `niveau` int(11) NOT NULL,
  `matiere` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

CREATE TABLE IF NOT EXISTS `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `doctrine_migration_versions`
--

INSERT IGNORE INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260201190054', '2026-02-05 23:48:00', 189),
('DoctrineMigrations\\Version20260205224754', '2026-02-05 23:48:19', 11),
('DoctrineMigrations\\Version20260205231019', '2026-02-06 00:10:23', 6),
('DoctrineMigrations\\Version20260206025736', '2026-02-06 03:58:50', 215);

-- --------------------------------------------------------

--
-- Structure de la table `evenement`
--

CREATE TABLE IF NOT EXISTS `evenement` (
  `id_evenement` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `date_evenement` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `type_evenement` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `localisation` varchar(500) DEFAULT NULL,
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `dislikes_count` int(11) NOT NULL DEFAULT 0,
  `favorites_count` int(11) NOT NULL DEFAULT 0,
  `nb_places_disponibles` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_evenement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Ajout des colonnes manquantes à la table `evenement` si elles n'existent pas
-- (Utile si la table existe déjà sans ces colonnes)
-- Note: Les erreurs "Duplicate column name" peuvent être ignorées
--

-- Procédure pour ajouter les colonnes si elles n'existent pas
DROP PROCEDURE IF EXISTS `add_evenement_columns_if_not_exists`;
DELIMITER $$
CREATE PROCEDURE `add_evenement_columns_if_not_exists`()
BEGIN
  DECLARE col_count INT DEFAULT 0;
  DECLARE CONTINUE HANDLER FOR 1060 BEGIN END; -- Duplicate column name
  
  -- Ajouter heure_debut si elle n'existe pas
  SELECT COUNT(*) INTO col_count
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'evenement' 
    AND COLUMN_NAME = 'heure_debut';
  IF col_count = 0 THEN
    ALTER TABLE `evenement` ADD COLUMN `heure_debut` time NOT NULL DEFAULT '00:00:00' AFTER `date_evenement`;
  END IF;
  
  -- Ajouter heure_fin si elle n'existe pas
  SELECT COUNT(*) INTO col_count
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'evenement' 
    AND COLUMN_NAME = 'heure_fin';
  IF col_count = 0 THEN
    ALTER TABLE `evenement` ADD COLUMN `heure_fin` time NOT NULL DEFAULT '23:59:59' AFTER `heure_debut`;
  END IF;
  
  -- Ajouter type_evenement si elle n'existe pas
  SELECT COUNT(*) INTO col_count
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'evenement' 
    AND COLUMN_NAME = 'type_evenement';
  IF col_count = 0 THEN
    ALTER TABLE `evenement` ADD COLUMN `type_evenement` varchar(50) DEFAULT NULL AFTER `heure_fin`;
  END IF;
  
  -- Ajouter nb_places_disponibles si elle n'existe pas
  SELECT COUNT(*) INTO col_count
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'evenement' 
    AND COLUMN_NAME = 'nb_places_disponibles';
  IF col_count = 0 THEN
    ALTER TABLE `evenement` ADD COLUMN `nb_places_disponibles` int(11) DEFAULT NULL AFTER `favorites_count`;
  END IF;
END$$
DELIMITER ;

-- Exécuter la procédure
CALL `add_evenement_columns_if_not_exists`();

-- Supprimer la procédure après utilisation
DROP PROCEDURE IF EXISTS `add_evenement_columns_if_not_exists`;

-- --------------------------------------------------------

--
-- Structure de la table `lecon`
--

CREATE TABLE IF NOT EXISTS `lecon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `ordre` int(11) NOT NULL,
  `media_type` varchar(255) NOT NULL,
  `media_url` varchar(255) NOT NULL,
  `cours_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_94E6242E7ECF78B0` (`cours_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `message`
--

CREATE TABLE IF NOT EXISTS `message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expediteur_id` int(11) NOT NULL,
  `contenu` varchar(255) NOT NULL,
  `date_envoi` datetime NOT NULL,
  `lu` tinyint(1) NOT NULL,
  `chat_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B6BD307F1A9A7125` (`chat_id`),
  KEY `IDX_B6BD307F10335F61` (`expediteur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messenger_messages`
--

CREATE TABLE IF NOT EXISTS `messenger_messages` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `body` longtext NOT NULL,
  `headers` longtext NOT NULL,
  `queue_name` varchar(190) NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produit`
--

CREATE TABLE IF NOT EXISTS `produit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `prix` int(11) NOT NULL,
  `type` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `question`
--

CREATE TABLE IF NOT EXISTS `question` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enonce` varchar(255) NOT NULL,
  `bonne_reponse` varchar(255) NOT NULL,
  `choix` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B6F7494E853CD175` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `quiz`
--

CREATE TABLE IF NOT EXISTS `quiz` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `score_max` varchar(255) NOT NULL,
  `cours_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A412FA927ECF78B0` (`cours_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ressource`
--

CREATE TABLE IF NOT EXISTS `ressource` (
  `id_ressource` int(11) NOT NULL AUTO_INCREMENT,
  `id_evenement` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `type_ressource` varchar(50) NOT NULL,
  `description` longtext DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  `date_debut` datetime DEFAULT NULL,
  `date_fin` datetime DEFAULT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_ressource`),
  KEY `IDX_ressource_evenement` (`id_evenement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(180) NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user`
--

INSERT IGNORE INTO `user` (`id`, `email`, `roles`, `password`, `first_name`, `last_name`, `is_active`) VALUES
(2, 'rami.benmohamed@esen.tn', '[\"ROLE_ADMIN\"]', '$2y$13$qBISviuNoLRyz3LePtSGSeulI2iPz7/tvtb2yOHs621s5.7iLzeU2', 'Rami', 'BenMohamed', 1),
(13, 'amin1@gmail.com', '[\"ROLE_ELEVE\"]', '$2y$13$3cjflEQTZbf/3G7hVCGHnu6gnloA4u.zlk49hWsTSzf7Q2rv6E89K', 'amin', 'masoudi', 1),
(14, 'mohamed@gmail.com', '[\"ROLE_PARENT\"]', '$2y$13$m2QwN.6wjvAIS4n/xvj4neCg3ZRjz5u4aJVwHwya3mCjpprEpyWLa', 'mohamed', 'karim', 1),
(15, 'issam@gmail.com', '[\"ROLE_PARENT\"]', '$2y$13$Ib4ykZm5y6mHJ90U9Rc6seGOq9UxcRzRX1kDabjUsRFv9bSbY72pC', 'issam', 'nebli', 1),
(16, 'karim@gmail.com', '[\"ROLE_ELEVE\"]', '$2y$13$YE7sMjxPspTEihAxyMNX9uY3FmN5/sra2tLbxMJfI9jBYFdebY6uG', 'karim', 'kimou', 1),
(17, 'aziz@gmail.com', '[\"ROLE_ELEVE\"]', '$2y$13$iKNDGHWNh.Xv5mB1qwHj8ekc9RUTEejGxvNhlrKN/PHFHJ7I2Z9XK', 'aziz', 'bougacha', 1),
(18, 'feriel@gmail.com', '[\"ROLE_PARENT\"]', '$2y$13$Rmd4kqBB5vMDqY94uQJ.lu58PgegUQouuQ774V1TXD301hoWv21oe', 'feriel222', 'souissi', 1),
(19, 'abdessalem.ghodbani@esprit.tn', '[\"ROLE_ELEVE\"]', '$2y$13$gOQ75ipy8kgmQ8MMcH950ekoj./rmiI7vUaxDemLwTULSfK0pxbri', 'abdessalem1', 'ghodbeny', 1),
(20, 'fersimouadh5@gmail.com', '[\"ROLE_ELEVE\"]', '$2y$13$D2g/jn0nJGj.pIipbBphOeEemBGUDtRkKcv40RKWu.uoIf5jSqbum', 'mouadh', 'fersi', 1);

-- --------------------------------------------------------

--
-- Structure de la table `programme`
--

CREATE TABLE IF NOT EXISTS `programme` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evenement_id` int(11) NOT NULL,
  `pause_debut` time NOT NULL,
  `pause_fin` time NOT NULL,
  `activites` longtext NOT NULL COMMENT 'Liste des activités prévues',
  `documents_requis` longtext NOT NULL COMMENT 'Documents obligatoires pour participer',
  `materiels_requis` longtext NOT NULL COMMENT 'Matériel nécessaire pour l''événement',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_programme_evenement` (`evenement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reservation`
--

CREATE TABLE IF NOT EXISTS `reservation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `id_evenement` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `prenom` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `telephone` varchar(255) DEFAULT NULL,
  `nb_adultes` int(11) NOT NULL DEFAULT 0,
  `nb_enfants` int(11) NOT NULL DEFAULT 0,
  `date_reservation` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_reservation_user` (`user_id`),
  KEY `IDX_reservation_evenement` (`id_evenement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user_evenement_interaction`
--

CREATE TABLE IF NOT EXISTS `user_evenement_interaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `evenement_id` int(11) NOT NULL,
  `type_interaction` varchar(20) NOT NULL COMMENT 'like, dislike, favorite',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_event_type` (`user_id`,`evenement_id`,`type_interaction`),
  KEY `IDX_interaction_user` (`user_id`),
  KEY `IDX_interaction_evenement` (`evenement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- AUTO_INCREMENT pour les tables déchargées
-- (Les clés primaires et index sont déjà définis dans les CREATE TABLE)
--

-- Les AUTO_INCREMENT sont déjà définis dans les CREATE TABLE avec AUTO_INCREMENT

--
-- Contraintes pour les tables déchargées
-- Suppression des contraintes existantes avant de les recréer
-- IMPORTANT: Si vous obtenez des erreurs "Unknown constraint" lors de la suppression,
-- vous pouvez les ignorer en toute sécurité - cela signifie simplement que les contraintes
-- n'existent pas encore.
--

-- Suppression des contraintes existantes
-- (Les erreurs peuvent être ignorées si les contraintes n'existent pas)
ALTER TABLE `commande` DROP FOREIGN KEY `FK_6EEAA67DA76ED395`;
ALTER TABLE `lecon` DROP FOREIGN KEY `FK_94E6242E7ECF78B0`;
ALTER TABLE `message` DROP FOREIGN KEY `FK_B6BD307F10335F61`;
ALTER TABLE `message` DROP FOREIGN KEY `FK_B6BD307F1A9A7125`;
ALTER TABLE `question` DROP FOREIGN KEY `FK_B6F7494E853CD175`;
ALTER TABLE `quiz` DROP FOREIGN KEY `FK_A412FA927ECF78B0`;
ALTER TABLE `ressource` DROP FOREIGN KEY `FK_ressource_evenement`;
ALTER TABLE `programme` DROP FOREIGN KEY `FK_programme_evenement`;
ALTER TABLE `reservation` DROP FOREIGN KEY `FK_reservation_user`;
ALTER TABLE `reservation` DROP FOREIGN KEY `FK_reservation_evenement`;
ALTER TABLE `user_evenement_interaction` DROP FOREIGN KEY `FK_interaction_evenement`;
ALTER TABLE `user_evenement_interaction` DROP FOREIGN KEY `FK_interaction_user`;

--
-- Contraintes pour la table `commande`
--
ALTER TABLE `commande`
  ADD CONSTRAINT `FK_6EEAA67DA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

--
-- Contraintes pour la table `lecon`
--
ALTER TABLE `lecon`
  ADD CONSTRAINT `FK_94E6242E7ECF78B0` FOREIGN KEY (`cours_id`) REFERENCES `cours` (`id`);

--
-- Contraintes pour la table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `FK_B6BD307F10335F61` FOREIGN KEY (`expediteur_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `FK_B6BD307F1A9A7125` FOREIGN KEY (`chat_id`) REFERENCES `chat` (`id`);

--
-- Contraintes pour la table `question`
--
ALTER TABLE `question`
  ADD CONSTRAINT `FK_B6F7494E853CD175` FOREIGN KEY (`quiz_id`) REFERENCES `quiz` (`id`);

--
-- Contraintes pour la table `quiz`
--
ALTER TABLE `quiz`
  ADD CONSTRAINT `FK_A412FA927ECF78B0` FOREIGN KEY (`cours_id`) REFERENCES `cours` (`id`);

--
-- Contraintes pour la table `ressource`
--
ALTER TABLE `ressource`
  ADD CONSTRAINT `FK_ressource_evenement` FOREIGN KEY (`id_evenement`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE;

--
-- Contraintes pour la table `programme`
--
ALTER TABLE `programme`
  ADD CONSTRAINT `FK_programme_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE;

--
-- Contraintes pour la table `reservation`
--
ALTER TABLE `reservation`
  ADD CONSTRAINT `FK_reservation_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_reservation_evenement` FOREIGN KEY (`id_evenement`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_evenement_interaction`
--
ALTER TABLE `user_evenement_interaction`
  ADD CONSTRAINT `FK_interaction_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_interaction_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

-- Réactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
