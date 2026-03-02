-- =====================================================
-- Script d'intégration des tables evenement et ressource
-- Base de données : EduKids
-- NE TOUCHE PAS aux autres tables (user, activite, etc.)
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Suppression uniquement de nos 3 tables si elles existent
DROP TABLE IF EXISTS `user_evenement_interaction`;
DROP TABLE IF EXISTS `ressource`;
DROP TABLE IF EXISTS `evenement`;

-- =====================================================
-- Table: evenement
-- =====================================================
CREATE TABLE `evenement` (
  `id_evenement` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_evenement` date NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `dislikes_count` int(11) NOT NULL DEFAULT 0,
  `favorites_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_evenement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: ressource
-- =====================================================
CREATE TABLE `ressource` (
  `id_ressource` int(11) NOT NULL AUTO_INCREMENT,
  `id_evenement` int(11) NOT NULL,
  `type_ressource` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` datetime DEFAULT NULL,
  `date_fin` datetime DEFAULT NULL,
  `fichier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_ressource`),
  KEY `IDX_ressource_evenement` (`id_evenement`),
  CONSTRAINT `FK_ressource_evenement` FOREIGN KEY (`id_evenement`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: user_evenement_interaction
-- Pour tracker les likes/dislikes/favoris par utilisateur
-- Référence la table user existante (id int(11))
-- =====================================================
CREATE TABLE `user_evenement_interaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `evenement_id` int(11) NOT NULL,
  `type_interaction` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'like, dislike, favorite',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_evenement_type` (`user_id`, `evenement_id`, `type_interaction`),
  KEY `IDX_interaction_user` (`user_id`),
  KEY `IDX_interaction_evenement` (`evenement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout des contraintes de clés étrangères séparément
ALTER TABLE `user_evenement_interaction`
  ADD CONSTRAINT `FK_interaction_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_interaction_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Données de test (optionnel)
-- =====================================================
INSERT INTO `evenement` (`titre`, `description`, `date_evenement`, `image`, `likes_count`, `dislikes_count`, `favorites_count`) VALUES
('Journée Portes Ouvertes', 'Venez découvrir notre établissement lors de notre journée portes ouvertes. Au programme : visites guidées, rencontres avec les enseignants et ateliers interactifs.', '2026-03-15', NULL, 5, 1, 3),
('Atelier Créatif pour Enfants', 'Un atelier de peinture et de dessin pour les enfants de 5 à 12 ans. Matériel fourni. Places limitées.', '2026-03-20', NULL, 8, 0, 6),
('Conférence Parentale', 'Comment accompagner son enfant dans ses apprentissages ? Conférence animée par des experts en pédagogie.', '2026-04-05', NULL, 12, 2, 10);

COMMIT;
