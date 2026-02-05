-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3307
-- Généré le : sam. 31 jan. 2026 à 00:07
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
-- Base de données : `lgpharma`
--

-- --------------------------------------------------------

--
-- Structure de la table `alertes`
--

CREATE TABLE `alertes` (
  `id` int(11) NOT NULL,
  `type` enum('stock_bas','rupture','peremption','vente_record') NOT NULL,
  `medicament_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `niveau` enum('info','warning','danger') DEFAULT 'warning',
  `vue` tinyint(1) DEFAULT 0,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_vue` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `nom`, `description`) VALUES
(1, 'Analgésiques', 'Médicaments contre la douleur'),
(2, 'Antibiotiques', 'Traitement des infections bactériennes'),
(3, 'Anti-inflammatoires', 'Réduction de l inflammation'),
(4, 'Gastro-intestinaux', 'Troubles digestifs'),
(5, 'Cardiovasculaires', 'Cœur et circulation sanguine'),
(6, 'Dermatologiques', 'Problèmes de peau'),
(7, 'Vitamines', 'Compléments alimentaires'),
(8, 'Premiers secours', 'Matériel de soins'),
(9, 'Hygiène', 'Produits d hygiène corporelle'),
(10, 'Médicaments enfants', 'Spécial enfants'),
(11, 'Produits naturels', 'Médicaments à base de plantes'),
(12, 'Antipaludéens', 'Traitement du paludisme');

-- --------------------------------------------------------

--
-- Structure de la table `medicaments`
--

CREATE TABLE `medicaments` (
  `id` int(11) NOT NULL,
  `nom` varchar(200) NOT NULL,
  `code_barre` varchar(50) DEFAULT NULL,
  `dci` varchar(200) DEFAULT NULL,
  `forme` varchar(50) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `laboratoire` varchar(100) DEFAULT NULL,
  `prix_achat` decimal(10,2) DEFAULT 0.00,
  `prix_vente` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `seuil_alerte` int(11) DEFAULT 10,
  `date_peremption` date DEFAULT NULL,
  `numero_lot` varchar(50) DEFAULT NULL,
  `fournisseur` varchar(100) DEFAULT NULL,
  `date_ajout` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `ajoute_par` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `medicaments`
--

INSERT INTO `medicaments` (`id`, `nom`, `code_barre`, `dci`, `forme`, `dosage`, `laboratoire`, `prix_achat`, `prix_vente`, `stock`, `seuil_alerte`, `date_peremption`, `numero_lot`, `fournisseur`, `date_ajout`, `date_modification`, `ajoute_par`) VALUES
(1, 'doliprane', '34000008865', '', 'Sirop', '500mg', 'shalina', 200.00, 300.00, 44, 10, '2026-01-30', '0001', 'upc', '2026-01-30 21:10:02', '2026-01-30 21:37:24', 1),
(2, 'para c', '4088746656', 'Paracetamol', 'Injectable', '50mg', 'shiza', 4000.00, 5000.00, 39, 10, '2026-01-31', '0002', 'shisapharma', '2026-01-30 21:55:25', '2026-01-30 21:58:09', 1);

-- --------------------------------------------------------

--
-- Structure de la table `medicaments_categories`
--

CREATE TABLE `medicaments_categories` (
  `medicament_id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parametres`
--

CREATE TABLE `parametres` (
  `id` int(11) NOT NULL,
  `cle` varchar(100) NOT NULL,
  `valeur` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `parametres`
--

INSERT INTO `parametres` (`id`, `cle`, `valeur`, `description`, `date_modification`) VALUES
(1, 'nom_pharmacie', 'LG PHARMA', 'Nom de la pharmacie', '2026-01-30 15:08:52'),
(2, 'pharmacienne', 'Jeanne', 'Nom de la pharmacienne', '2026-01-30 15:08:52'),
(3, 'adresse', 'Binanga Numero 32, Kinshasa', 'Adresse de la pharmacie', '2026-01-30 15:08:52'),
(4, 'telephone', '+243 0812475527', 'Numéro de téléphone', '2026-01-30 15:08:52'),
(5, 'email_contact', 'jeannengbo3@gmail.com', 'Email de contact', '2026-01-30 15:08:52'),
(6, 'seuil_stock_bas', '10', 'Seuil pour alerte stock bas', '2026-01-30 15:08:52'),
(7, 'jours_alerte_peremption', '30', 'Jours avant péremption pour alerte', '2026-01-30 15:08:52'),
(8, 'devise', '$', 'Devise utilisée', '2026-01-30 15:08:52'),
(9, 'timezone', 'Africa/Kinshasa', 'Fuseau horaire', '2026-01-30 15:08:52'),
(10, 'pays', 'RDC', 'Pays', '2026-01-30 15:08:52'),
(11, 'ville', 'Kinshasa', 'Ville', '2026-01-30 15:08:52');

-- --------------------------------------------------------

--
-- Structure de la table `rapports_jour`
--

CREATE TABLE `rapports_jour` (
  `id` int(11) NOT NULL,
  `date_jour` date NOT NULL,
  `chiffre_affaires` decimal(10,2) DEFAULT 0.00,
  `nb_ventes` int(11) DEFAULT 0,
  `nb_clients` int(11) DEFAULT 0,
  `medicaments_vendus` int(11) DEFAULT 0,
  `stock_bas` int(11) DEFAULT 0,
  `ruptures` int(11) DEFAULT 0,
  `meilleur_medicament` varchar(100) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rapports_mois`
--

CREATE TABLE `rapports_mois` (
  `id` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `mois` int(11) NOT NULL,
  `mois_nom` varchar(20) NOT NULL,
  `chiffre_affaires` decimal(10,2) DEFAULT 0.00,
  `cout_achats` decimal(10,2) DEFAULT 0.00,
  `marge` decimal(10,2) DEFAULT 0.00,
  `taux_marge` decimal(5,2) DEFAULT 0.00,
  `nb_ventes` int(11) DEFAULT 0,
  `panier_moyen` decimal(10,2) DEFAULT 0.00,
  `meilleur_jour` date DEFAULT NULL,
  `meilleur_jour_ca` decimal(10,2) DEFAULT NULL,
  `top_5_medicaments` text DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rapports_semaine`
--

CREATE TABLE `rapports_semaine` (
  `id` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `semaine` int(11) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `chiffre_affaires` decimal(10,2) DEFAULT 0.00,
  `nb_ventes` int(11) DEFAULT 0,
  `meilleur_jour` date DEFAULT NULL,
  `meilleur_jour_ca` decimal(10,2) DEFAULT NULL,
  `top_medicament` varchar(100) DEFAULT NULL,
  `top_medicament_qty` int(11) DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT 'default-avatar.png',
  `role` enum('admin','employe') DEFAULT 'employe',
  `premier_login` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `derniere_connexion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `prenom`, `nom`, `email`, `password`, `photo`, `role`, `premier_login`, `date_creation`, `derniere_connexion`) VALUES
(1, 'Jeanne', 'Ngbo', 'jeannengbo3@gmail.com', '$2y$10$1t9Hr0yOSAwkuzUKWexgj.ASaiaFJX3WWBTHEkB9hWz2d4oPJmLEK', 'jeanne-avatar.png', 'admin', 0, '2026-01-30 15:11:49', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `ventes`
--

CREATE TABLE `ventes` (
  `id` int(11) NOT NULL,
  `medicament_id` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `client_nom` varchar(100) DEFAULT NULL,
  `vendeur_id` int(11) DEFAULT NULL,
  `date_vente` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `ventes`
--

INSERT INTO `ventes` (`id`, `medicament_id`, `quantite`, `prix_unitaire`, `total`, `client_nom`, `vendeur_id`, `date_vente`) VALUES
(1, 1, 1, 300.00, 300.00, NULL, 1, '2026-01-30 21:37:24'),
(2, 2, 1, 5000.00, 5000.00, NULL, 1, '2026-01-30 21:58:09');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_vue` (`vue`),
  ADD KEY `idx_date` (`date_creation`),
  ADD KEY `medicament_id` (`medicament_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`),
  ADD KEY `idx_nom` (`nom`);

--
-- Index pour la table `medicaments`
--
ALTER TABLE `medicaments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_barre` (`code_barre`),
  ADD KEY `idx_nom` (`nom`),
  ADD KEY `idx_stock` (`stock`),
  ADD KEY `idx_peremption` (`date_peremption`),
  ADD KEY `ajoute_par` (`ajoute_par`);

--
-- Index pour la table `medicaments_categories`
--
ALTER TABLE `medicaments_categories`
  ADD PRIMARY KEY (`medicament_id`,`categorie_id`),
  ADD KEY `categorie_id` (`categorie_id`);

--
-- Index pour la table `parametres`
--
ALTER TABLE `parametres`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`),
  ADD KEY `idx_cle` (`cle`);

--
-- Index pour la table `rapports_jour`
--
ALTER TABLE `rapports_jour`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date_jour` (`date_jour`),
  ADD KEY `idx_date` (`date_jour`);

--
-- Index pour la table `rapports_mois`
--
ALTER TABLE `rapports_mois`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mois` (`annee`,`mois`),
  ADD KEY `idx_mois` (`mois`,`annee`);

--
-- Index pour la table `rapports_semaine`
--
ALTER TABLE `rapports_semaine`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_semaine` (`annee`,`semaine`),
  ADD KEY `idx_dates` (`date_debut`,`date_fin`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Index pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date_vente`),
  ADD KEY `idx_medicament` (`medicament_id`),
  ADD KEY `idx_vendeur` (`vendeur_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `alertes`
--
ALTER TABLE `alertes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `medicaments`
--
ALTER TABLE `medicaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `parametres`
--
ALTER TABLE `parametres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `rapports_jour`
--
ALTER TABLE `rapports_jour`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rapports_mois`
--
ALTER TABLE `rapports_mois`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rapports_semaine`
--
ALTER TABLE `rapports_semaine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `ventes`
--
ALTER TABLE `ventes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alertes`
--
ALTER TABLE `alertes`
  ADD CONSTRAINT `alertes_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `medicaments`
--
ALTER TABLE `medicaments`
  ADD CONSTRAINT `medicaments_ibfk_1` FOREIGN KEY (`ajoute_par`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `medicaments_categories`
--
ALTER TABLE `medicaments_categories`
  ADD CONSTRAINT `medicaments_categories_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `medicaments_categories_ibfk_2` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `ventes`
--
ALTER TABLE `ventes`
  ADD CONSTRAINT `ventes_ibfk_1` FOREIGN KEY (`medicament_id`) REFERENCES `medicaments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ventes_ibfk_2` FOREIGN KEY (`vendeur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
