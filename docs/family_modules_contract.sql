-- ============================================================
-- LabTrack - Espace famille : tables attendues des AUTRES modules
-- ------------------------------------------------------------
-- L'Espace famille LIT ces tables si elles existent (sinon il affiche "bientôt").
-- Chaque membre garde la main sur SON module : ce fichier sert seulement à se mettre
-- d'accord sur les noms. Les colonnes listées ici sont le minimum lu par la famille ;
-- vous pouvez en ajouter d'autres librement.
--
-- RÈGLE COMMUNE : le propriétaire d'une ligne est
--   - soit un compte  : user_id   -> utilisateur(id)
--   - soit un profil à charge (enfant, grand-parent sans compte) : membre_id -> famille_membre(id)
-- (une des deux colonnes est remplie, l'autre est NULL)
--
-- Pour tester l'Espace famille avant que les modules existent, exécutez ce fichier
-- dans phpMyAdmin (base labtrack) APRÈS database.sql (les clés étrangères pointent vers
-- famille_membre) : il crée des tables VIDES, à remplir à la main.
-- ============================================================

USE labtrack;

-- Module "Rappels & visites" : DÉJÀ FAIT (branche feature/reminders-visits, RappelController).
-- Les 3 tables ci-dessous sont dans database.sql avec leurs colonnes complètes (statut, note...).
-- rendez-vous -> calendrier, alertes, cartes des membres
CREATE TABLE IF NOT EXISTS rendez_vous (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id   INT NULL,
    membre_id INT NULL,
    titre     VARCHAR(150) NOT NULL,        -- "Pédiatre", "Prise de sang"...
    date_rdv  DATETIME NOT NULL,
    lieu      VARCHAR(150) NULL,
    medecin   VARCHAR(100) NULL,
    CONSTRAINT fk_rdv_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    CONSTRAINT fk_rdv_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module "Rappels & visites" (Aziz) : un médicament à une heure, chaque jour entre deux dates
CREATE TABLE IF NOT EXISTS rappel_medicament (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    membre_id  INT NULL,
    medicament VARCHAR(100) NOT NULL,
    dose       VARCHAR(50) NULL,              -- "1 comprimé", "500 mg"
    heure      TIME NOT NULL,
    date_debut DATE NOT NULL,
    date_fin   DATE NULL,                     -- NULL = traitement sans fin prévue
    CONSTRAINT fk_rappel_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    CONSTRAINT fk_rappel_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module "Rappels & visites" (Aziz) : "pris" coché par le patient -> statistiques et alertes
CREATE TABLE IF NOT EXISTS prise_medicament (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    rappel_id  INT NOT NULL,
    date_prise DATE NOT NULL,
    statut     ENUM('pris','oublie') NOT NULL DEFAULT 'pris',
    UNIQUE KEY uq_prise (rappel_id, date_prise),
    CONSTRAINT fk_prise_rappel FOREIGN KEY (rappel_id) REFERENCES rappel_medicament(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module "Analyses" (Ismail) : un bilan, et s'il contient une valeur hors norme -> alerte douce
CREATE TABLE IF NOT EXISTS bilan_sanguin (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    membre_id  INT NULL,
    titre      VARCHAR(150) NOT NULL,         -- "NFS", "Glycémie"
    date_bilan DATE NOT NULL,
    hors_norme TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_bilan_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
    CONSTRAINT fk_bilan_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module "Infos santé & alertes" (Fadi) : campagnes affichées aux familles concernées
CREATE TABLE IF NOT EXISTS campagne_sante (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    titre      VARCHAR(200) NOT NULL,         -- "Vaccination contre la grippe"
    public     ENUM('tous','enfants','aines') NOT NULL DEFAULT 'tous',
    date_debut DATE NOT NULL,
    date_fin   DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
