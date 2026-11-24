-- ============================================================
-- LabTrack - Module Gestion des utilisateurs
-- Base de données : labtrack
-- À exécuter dans phpMyAdmin (onglet SQL)
-- ============================================================

CREATE DATABASE IF NOT EXISTS labtrack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE labtrack;

-- Table centrale UTILISATEUR (voir le modèle de données LabTrack)
-- Toutes les autres tables du projet (dossier, bilan_sanguin, medicament,
-- rendez_vous, simulation, espace_famille, favori, alerte...) y feront
-- référence via une clé étrangère user_id.
-- email UNIQUE : impossible d'avoir deux comptes avec le même email.
--   Une fois le compte supprimé, l'email peut servir à un nouveau compte.
-- code : code personnel à 4 chiffres (#0427), unique, visible uniquement
--   par le propriétaire du compte. Un nouveau compte reçoit un nouveau code.
-- session_token : jeton de la DERNIÈRE connexion. Une nouvelle connexion le
--   remplace, ce qui ferme la session ouverte ailleurs (une seule à la fois).
-- failed_attempts / locked_until : anti force brute. Après 5 mots de passe
--   faux, le compte est bloqué 15 minutes (même avec le bon mot de passe).
CREATE TABLE IF NOT EXISTS utilisateur (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    code          CHAR(4) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('patient', 'admin') NOT NULL DEFAULT 'patient',
    langue        ENUM('fr', 'ar', 'en') NOT NULL DEFAULT 'fr',
    session_token CHAR(64) NULL DEFAULT NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until  DATETIME NULL DEFAULT NULL,
    avatar        VARCHAR(64) NULL DEFAULT NULL,   -- nom du fichier photo (storage/avatars/), NULL = initiales
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Base déjà créée avant la photo de profil : la colonne est ajoutée automatiquement
-- au premier envoi de photo (AuthController::ensureAvatarColumn), ou à la main :
-- ALTER TABLE utilisateur ADD COLUMN avatar VARCHAR(64) NULL DEFAULT NULL AFTER locked_until;

-- ------------------------------------------------------------
-- Mot de passe oublié : un code à 6 chiffres envoyé par email, valable 5 minutes.
-- code_hash : le code haché (password_hash), jamais en clair.
-- attempts  : mauvais codes saisis (au 5e, le code est supprimé).
-- Table créée automatiquement au premier usage (AuthController::ensureResetTable).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset (
    user_id    INT PRIMARY KEY,
    code_hash  VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Compte administrateur de test (optionnel)
-- Email : admin@labtrack.tn  /  Mot de passe : Admin@1234
-- Le mot de passe est déjà haché avec password_hash() : jamais en clair.
-- (Vous pouvez aussi, comme dans le workshop 6.4, créer un compte via
--  le formulaire puis changer son rôle en 'admin' dans phpMyAdmin.)
-- INSERT IGNORE : le script peut être relancé sans erreur
-- (l'email étant UNIQUE, le compte n'est pas créé une deuxième fois).
-- ------------------------------------------------------------
INSERT IGNORE INTO utilisateur (nom, email, code, password, role, langue) VALUES
('Administrateur LabTrack', 'admin@labtrack.tn', '7305',
 '$2y$10$bIO5dyAY0F8k2BDyWudfyOddPCNB/5QMjCd5tbpjyq2AXnIX.LnRW', 'admin', 'fr');

-- ------------------------------------------------------------
-- Mise à jour d'une base créée AVANT l'ajout du code et de la session unique
-- (à exécuter une seule fois si votre table n'a pas encore ces colonnes)
-- ------------------------------------------------------------
-- ALTER TABLE utilisateur ADD COLUMN code CHAR(4) NULL AFTER email,
--                         ADD COLUMN session_token CHAR(64) NULL DEFAULT NULL AFTER langue;
-- UPDATE utilisateur SET code = LPAD(id, 4, '0') WHERE code IS NULL;
-- ALTER TABLE utilisateur MODIFY code CHAR(4) NOT NULL, ADD UNIQUE (code);

-- ------------------------------------------------------------
-- Mise à jour d'une base créée AVANT l'ajout de l'anti force brute
-- ------------------------------------------------------------
-- ALTER TABLE utilisateur
--   ADD COLUMN failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER session_token,
--   ADD COLUMN locked_until DATETIME NULL DEFAULT NULL AFTER failed_attempts;

-- ------------------------------------------------------------
-- Mise à jour d'une base déjà créée avec l'ancien rôle 'medecin'
-- (à exécuter seulement si votre table contient encore ce rôle)
-- ------------------------------------------------------------
-- UPDATE utilisateur SET role = 'patient' WHERE role = 'medecin';
-- ALTER TABLE utilisateur MODIFY role ENUM('patient', 'admin') NOT NULL DEFAULT 'patient';
