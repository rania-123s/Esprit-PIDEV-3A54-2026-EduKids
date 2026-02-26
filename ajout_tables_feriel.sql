-- =====================================================
-- Script pour AJOUTER les tables de Feriel
-- À exécuter sur une base edukids EXISTANTE
-- =====================================================

-- =====================================================
-- Table: evenement
-- =====================================================
CREATE TABLE IF NOT EXISTS `evenement` (
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
CREATE TABLE IF NOT EXISTS `ressource` (
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
-- =====================================================
CREATE TABLE IF NOT EXISTS `user_evenement_interaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `evenement_id` int(11) NOT NULL,
  `type_interaction` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'like, dislike, favorite',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_evenement_type` (`user_id`, `evenement_id`, `type_interaction`),
  KEY `IDX_interaction_user` (`user_id`),
  KEY `IDX_interaction_evenement` (`evenement_id`),
  CONSTRAINT `FK_interaction_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_interaction_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Données de test
-- =====================================================
INSERT INTO `evenement` (`titre`, `description`, `date_evenement`, `image`, `likes_count`, `dislikes_count`, `favorites_count`) VALUES
('Journée Portes Ouvertes', 'Venez découvrir notre établissement lors de notre journée portes ouvertes. Au programme : visites guidées, rencontres avec les enseignants et ateliers interactifs.', '2026-03-15', NULL, 5, 1, 3),
('Atelier Créatif pour Enfants', 'Un atelier de peinture et de dessin pour les enfants de 5 à 12 ans. Matériel fourni. Places limitées.', '2026-03-20', NULL, 8, 0, 6),
('Conférence Parentale', 'Comment accompagner son enfant dans ses apprentissages ? Conférence animée par des experts en pédagogie.', '2026-04-05', NULL, 12, 2, 10);
