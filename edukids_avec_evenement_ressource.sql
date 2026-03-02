-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 17 fév. 2026 à 20:45
-- Version du serveur : 10.4.24-MariaDB
-- Version de PHP : 8.1.6

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

-- --------------------------------------------------------

--
-- Structure de la table `activite`
--

CREATE TABLE `activite` (
  `id` int(11) NOT NULL,
  `nom_activite` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lieu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat`
--

CREATE TABLE `chat` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `date_creation` datetime NOT NULL,
  `dernier_message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_dernier_message` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande`
--

CREATE TABLE `commande` (
  `id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `montant_total` int(11) NOT NULL,
  `statut` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `cours`
--

CREATE TABLE `cours` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` int(11) NOT NULL,
  `matiere` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `doctrine_migration_versions`
--

CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Déchargement des données de la table `doctrine_migration_versions`
--

INSERT INTO `doctrine_migration_versions` (`version`, `executed_at`, `execution_time`) VALUES
('DoctrineMigrations\\Version20260201190054', '2026-02-05 23:48:00', 189),
('DoctrineMigrations\\Version20260205224754', '2026-02-05 23:48:19', 11),
('DoctrineMigrations\\Version20260205231019', '2026-02-06 00:10:23', 6),
('DoctrineMigrations\\Version20260206025736', '2026-02-06 03:58:50', 215);

-- --------------------------------------------------------

--
-- Structure de la table `event`
--

CREATE TABLE `event` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_heure` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `evenement` (TABLE FERIEL)
--

CREATE TABLE `evenement` (
  `id_evenement` int(11) NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_evenement` date NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `dislikes_count` int(11) NOT NULL DEFAULT 0,
  `favorites_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `evenement`
--

INSERT INTO `evenement` (`id_evenement`, `titre`, `description`, `date_evenement`, `image`, `likes_count`, `dislikes_count`, `favorites_count`) VALUES
(1, 'Journée Portes Ouvertes', 'Venez découvrir notre établissement lors de notre journée portes ouvertes. Au programme : visites guidées, rencontres avec les enseignants et ateliers interactifs.', '2026-03-15', NULL, 5, 1, 3),
(2, 'Atelier Créatif pour Enfants', 'Un atelier de peinture et de dessin pour les enfants de 5 à 12 ans. Matériel fourni. Places limitées.', '2026-03-20', NULL, 8, 0, 6),
(3, 'Conférence Parentale', 'Comment accompagner son enfant dans ses apprentissages ? Conférence animée par des experts en pédagogie.', '2026-04-05', NULL, 12, 2, 10);

-- --------------------------------------------------------

--
-- Structure de la table `lecon`
--

CREATE TABLE `lecon` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordre` int(11) NOT NULL,
  `media_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cours_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `message`
--

CREATE TABLE `message` (
  `id` int(11) NOT NULL,
  `expediteur_id` int(11) NOT NULL,
  `contenu` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_envoi` datetime NOT NULL,
  `lu` tinyint(1) NOT NULL,
  `chat_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messenger_messages`
--

CREATE TABLE `messenger_messages` (
  `id` bigint(20) NOT NULL,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produit`
--

CREATE TABLE `produit` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prix` int(11) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `question`
--

CREATE TABLE `question` (
  `id` int(11) NOT NULL,
  `enonce` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bonne_reponse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `choix` int(11) NOT NULL,
  `quiz_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `quiz`
--

CREATE TABLE `quiz` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score_max` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cours_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `ressource` (TABLE FERIEL)
--

CREATE TABLE `ressource` (
  `id_ressource` int(11) NOT NULL,
  `id_evenement` int(11) NOT NULL,
  `type_ressource` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_debut` datetime DEFAULT NULL,
  `date_fin` datetime DEFAULT NULL,
  `fichier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`roles`)),
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user`
--

INSERT INTO `user` (`id`, `email`, `roles`, `password`, `first_name`, `last_name`, `is_active`) VALUES
(2, 'rami.benmohamed@esen.tn', '[\"ROLE_ADMIN\"]', '$2y$13$qBISviuNoLRyz3LePtSGSeulI2iPz7/tvtb2yOHs621s5.7iLzeU2', 'Rami', 'BenMohamed', 1),
(13, 'amin1@gmail.com', '[\"ROLE_ELEVE\"]', '$2y$13$3cjflEQTZbf/3G7hVCGHnu6gnloA4u.zlk49hWsTSzf7Q2rv6E89K', 'amin', 'masoudi', 1),
(14, 'mohamed@gmail.com', '[\"ROLE_PARENT\"]', '$2y$13$m2QwN.6wjvAIS4n/xvj4neCg3ZRjz5u4aJVwHwya3mCjpprEpyWLa', 'mohamed', 'karim', 1),
(15, 'issam@gmail.com', '[\"ROLE_PARENT\"]', '$2y$13$Ib4ykZm5y6mHJ90U9Rc6seGOq9UxcRzRX1kDabjUsRFv9bSbY72pC', 'issam', 'nebli', 1),
(16, 'karim@gmail.com', '[\"ROLE_ELEVE\"]', '$2y$13$YE7sMjxPspTEihAxyMNX9uY3FmN5/sra2tLbxMJfI9jBYFdebY6uG', 'karim', 'kimou', 1),
(17, 'aziz@gmail.com', '[\"ROLE_ELEVE\"]', '$2y$13$iKNDGHWNh.Xv5mB1qwHj8ekc9RUTEejGxvNhlrKN/PHFHJ7I2Z9XK', 'aziz', 'bougacha', 1),
(18, 'feriel@gmail.com', '[\"ROLE_PARENT\"]', '$2y$13$Rmd4kqBB5vMDqY94uQJ.lu58PgegUQouuQ774V1TXD301hoWv21oe', 'feriel222', 'souissi', 1),
(19, 'abdessalem.ghodbani@esprit.tn', '[\"ROLE_ELEVE\"]', '$2y$13$gOQ75ipy8kgmQ8MMcH950ekoj./rmiI7vUaxDemLwTULSfK0pxbri', 'abdessalem1', 'ghodbeny', 1);

-- --------------------------------------------------------

--
-- Structure de la table `user_evenement_interaction` (TABLE FERIEL - INTERMEDIAIRE)
--

CREATE TABLE `user_evenement_interaction` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `evenement_id` int(11) NOT NULL,
  `type_interaction` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'like, dislike, favorite',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `activite`
--
ALTER TABLE `activite`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_B875551571F7E88B` (`event_id`);

--
-- Index pour la table `chat`
--
ALTER TABLE `chat`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `commande`
--
ALTER TABLE `commande`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_6EEAA67DA76ED395` (`user_id`);

--
-- Index pour la table `cours`
--
ALTER TABLE `cours`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `doctrine_migration_versions`
--
ALTER TABLE `doctrine_migration_versions`
  ADD PRIMARY KEY (`version`);

--
-- Index pour la table `event`
--
ALTER TABLE `event`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `evenement` (TABLE FERIEL)
--
ALTER TABLE `evenement`
  ADD PRIMARY KEY (`id_evenement`);

--
-- Index pour la table `lecon`
--
ALTER TABLE `lecon`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_94E6242E7ECF78B0` (`cours_id`);

--
-- Index pour la table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_B6BD307F1A9A7125` (`chat_id`),
  ADD KEY `IDX_B6BD307F10335F61` (`expediteur_id`);

--
-- Index pour la table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750` (`queue_name`,`available_at`,`delivered_at`,`id`);

--
-- Index pour la table `produit`
--
ALTER TABLE `produit`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `question`
--
ALTER TABLE `question`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_B6F7494E853CD175` (`quiz_id`);

--
-- Index pour la table `quiz`
--
ALTER TABLE `quiz`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_A412FA927ECF78B0` (`cours_id`);

--
-- Index pour la table `ressource` (TABLE FERIEL)
--
ALTER TABLE `ressource`
  ADD PRIMARY KEY (`id_ressource`),
  ADD KEY `IDX_ressource_evenement` (`id_evenement`);

--
-- Index pour la table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`);

--
-- Index pour la table `user_evenement_interaction` (TABLE FERIEL)
--
ALTER TABLE `user_evenement_interaction`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_evenement_type` (`user_id`, `evenement_id`, `type_interaction`),
  ADD KEY `IDX_interaction_user` (`user_id`),
  ADD KEY `IDX_interaction_evenement` (`evenement_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `activite`
--
ALTER TABLE `activite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat`
--
ALTER TABLE `chat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commande`
--
ALTER TABLE `commande`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `cours`
--
ALTER TABLE `cours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `event`
--
ALTER TABLE `event`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `evenement` (TABLE FERIEL)
--
ALTER TABLE `evenement`
  MODIFY `id_evenement` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `lecon`
--
ALTER TABLE `lecon`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `message`
--
ALTER TABLE `message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messenger_messages`
--
ALTER TABLE `messenger_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `produit`
--
ALTER TABLE `produit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `question`
--
ALTER TABLE `question`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `quiz`
--
ALTER TABLE `quiz`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ressource` (TABLE FERIEL)
--
ALTER TABLE `ressource`
  MODIFY `id_ressource` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `user_evenement_interaction` (TABLE FERIEL)
--
ALTER TABLE `user_evenement_interaction`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `activite`
--
ALTER TABLE `activite`
  ADD CONSTRAINT `FK_B875551571F7E88B` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`);

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
-- Contraintes pour la table `ressource` (TABLE FERIEL)
--
ALTER TABLE `ressource`
  ADD CONSTRAINT `FK_ressource_evenement` FOREIGN KEY (`id_evenement`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE;

--
-- Contraintes pour la table `user_evenement_interaction` (TABLE FERIEL)
--
ALTER TABLE `user_evenement_interaction`
  ADD CONSTRAINT `FK_interaction_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_interaction_evenement` FOREIGN KEY (`evenement_id`) REFERENCES `evenement` (`id_evenement`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
