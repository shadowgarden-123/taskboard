-- ============================================================
-- TaskBoard — Base de données
-- Import : phpMyAdmin > Importer, ou : mysql -u root < taskboard.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS taskboard
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE taskboard;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NULL,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    date_limite DATE NULL,
    priorite ENUM('basse','moyenne','haute') DEFAULT 'moyenne',
    statut ENUM('a_faire','terminee') DEFAULT 'a_faire',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_tasks_user (user_id),
    INDEX idx_tasks_categorie (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- Jeu de données de test
-- Mot de passe des deux comptes : test123
-- ------------------------------------------------------------

INSERT INTO users (id, nom, email, mot_de_passe) VALUES
(1, 'Amina Diallo', 'etudiant1@test.com', '$2y$10$jDfp.NLXfNqP09PZAVmEt.FvJnfVEQEzvcC6E/e/Cafmp7skSkUdu'),
(2, 'Lucas Martin', 'etudiant2@test.com', '$2y$10$jDfp.NLXfNqP09PZAVmEt.FvJnfVEQEzvcC6E/e/Cafmp7skSkUdu');

INSERT INTO categories (id, user_id, nom) VALUES
(1, 1, 'Cours'),
(2, 1, 'Perso'),
(3, 1, 'Projet'),
(4, 2, 'Cours'),
(5, 2, 'Perso'),
(6, 2, 'Projet');

INSERT INTO tasks (id, user_id, category_id, titre, description, date_limite, priorite, statut) VALUES
(1, 1, 3, 'Rendre le rapport de stage',
 'Finaliser la mise en page, relire l''introduction et déposer le PDF sur la plateforme.',
 DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'haute', 'a_faire'),
(2, 1, 1, 'Réviser le chapitre 4 de bases de données',
 'Requêtes préparées, jointures et index. Refaire les exercices 3 à 7.',
 DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'moyenne', 'a_faire'),
(3, 1, 2, 'Acheter des fournitures',
 'Cartouches d''encre, bloc-notes et chemises pour les cours.',
 DATE_ADD(CURDATE(), INTERVAL -2 DAY), 'basse', 'a_faire'),
(4, 1, 3, 'Préparer la soutenance',
 'Plan de dix minutes, diapositives et une répétition à blanc.',
 DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'haute', 'a_faire'),
(5, 1, 2, 'Nettoyer la boîte mail',
 'Trier les newsletters et archiver les anciens échanges.',
 NULL, 'basse', 'terminee'),
(6, 2, 4, 'Préparer l''exposé d''anglais',
 'Diapositives sur le travail à distance, huit minutes de présentation.',
 DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'haute', 'a_faire'),
(7, 2, 5, 'Inscription au club de sport',
 'Choisir un créneau le mardi ou le jeudi.',
 DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'basse', 'a_faire'),
(8, 2, 5, 'Réserver le train pour les vacances',
 'Aller-retour, départ le vendredi soir.',
 DATE_ADD(CURDATE(), INTERVAL -3 DAY), 'moyenne', 'a_faire'),
(9, 2, 5, 'Ranger le bureau',
 'Trier les documents et jeter les brouillons.',
 NULL, 'basse', 'terminee'),
(10, 2, 6, 'Rendre le dossier de groupe',
 'Vérifier les annexes et la bibliographie avant l''envoi.',
 DATE_ADD(CURDATE(), INTERVAL -5 DAY), 'haute', 'terminee');
