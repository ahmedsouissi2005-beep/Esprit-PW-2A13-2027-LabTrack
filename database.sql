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

-- ============================================================
-- ESPACE FAMILLE (Ahmed)
-- Tables aussi créées automatiquement au premier usage (FamilyController::ensureTables).
-- Détails et données attendues des autres modules : docs/FAMILY_SPACE.md
-- ============================================================

-- Une famille : son gestionnaire est le membre de rôle 'manager' (famille_membre)
CREATE TABLE IF NOT EXISTS famille (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(60) NOT NULL,
    created_by INT NULL,                               -- compte qui l'a créée (information)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_famille_user FOREIGN KEY (created_by) REFERENCES utilisateur(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membres : un compte LabTrack (user_id) OU un profil à charge (user_id NULL, nom + date de naissance).
-- share_* : consentement du membre (ce que la famille peut voir), modifiable à tout moment.
-- UNIQUE (famille_id, user_id) : un compte n'est qu'une fois dans une famille.
CREATE TABLE IF NOT EXISTS famille_membre (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    famille_id     INT NOT NULL,
    user_id        INT NULL,
    nom            VARCHAR(100) NULL,
    relation       VARCHAR(20) NOT NULL DEFAULT 'autre',
    date_naissance DATE NULL,
    role           ENUM('manager','caregiver','member') NOT NULL DEFAULT 'member',
    share_analyses TINYINT(1) NOT NULL DEFAULT 0,
    share_dossier  TINYINT(1) NOT NULL DEFAULT 0,
    share_rappels  TINYINT(1) NOT NULL DEFAULT 0,
    share_rdv      TINYINT(1) NOT NULL DEFAULT 0,
    joined_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_famille_user (famille_id, user_id),
    CONSTRAINT fk_membre_famille FOREIGN KEY (famille_id) REFERENCES famille(id) ON DELETE CASCADE,
    CONSTRAINT fk_membre_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitations par nom + code privé, valables 48 h. membre_id : relier un profil à charge au compte invité.
CREATE TABLE IF NOT EXISTS famille_invitation (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    famille_id      INT NOT NULL,
    invited_by      INT NOT NULL,
    invited_user_id INT NOT NULL,
    membre_id       INT NULL,
    relation        VARCHAR(20) NOT NULL DEFAULT 'autre',
    status          ENUM('pending','accepted','refused','expired') NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,
    responded_at    DATETIME NULL,
    CONSTRAINT fk_inv_famille FOREIGN KEY (famille_id) REFERENCES famille(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_by FOREIGN KEY (invited_by) REFERENCES utilisateur(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_user FOREIGN KEY (invited_user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages privés de la famille
CREATE TABLE IF NOT EXISTS famille_message (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    famille_id INT NOT NULL,
    user_id    INT NOT NULL,
    contenu    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_famille FOREIGN KEY (famille_id) REFERENCES famille(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carte d'urgence : d'un compte (user_id) OU d'un profil à charge (membre_id).
-- token : 32 caractères aléatoires du QR code (NULL = QR code désactivé).
CREATE TABLE IF NOT EXISTS fiche_urgence (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL UNIQUE,
    membre_id       INT NULL UNIQUE,
    groupe_sanguin  VARCHAR(3) NULL,
    allergies       TEXT NULL,
    maladies        TEXT NULL,
    traitements     TEXT NULL,
    contact_urgence VARCHAR(150) NULL,
    token           CHAR(32) NULL UNIQUE,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_fiche_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    CONSTRAINT fk_fiche_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Codes faux saisis pour inviter (anti devinette) : 5 en 15 minutes = blocage 15 minutes
CREATE TABLE IF NOT EXISTS famille_tentative (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tentative (user_id, created_at),
    CONSTRAINT fk_tentative_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
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
