<?php
/**
 * controllers/BilanController.php
 * Module "Analyses sanguines" de LabTrack (Ismail).
 *
 * Flux : envoi du rapport (PDF/photo) -> lecture par l'IA (config/AiExtractor.php)
 *        -> le patient RELIT et corrige les valeurs -> enregistrement.
 * Chaque valeur est comparée à la norme imprimée sur le rapport (bas / normal / haut).
 * Si une valeur est hors norme, bilan_sanguin.hors_norme = 1 : l'Espace famille
 * affiche alors une alerte douce aux aidants (docs/family_modules_contract.sql).
 *
 * LabTrack aide à comprendre, le médecin décide : aucun diagnostic n'est posé ici.
 * Sécurité : chaque bilan est relu avec "appartient à quelqu'un que je gère", requêtes
 * préparées, CSRF sur chaque POST, type réel du fichier vérifié, fichier servi par PHP.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/AiExtractor.php';

class BilanController extends Controller
{
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 Mo (limite des images de l'API)
    private const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    private PDO $db;
    private array $t;

    public function __construct()
    {
        $this->db = Database::getConnexion();
        $this->t  = AuthController::loadTranslations();
        if (empty($_SESSION['family_tables_v1'])) {
            new FamilyController(); // les profils à charge viennent de l'Espace famille
        }
        $this->ensureTables();
    }

    // ==============================================================
    // PAGES
    // ==============================================================

    /** Liste de mes bilans (et de ceux de mes profils à charge) */
    public function home(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        $rows = $this->db->query(
            'SELECT b.*, (SELECT COUNT(*) FROM resultat_analyse r WHERE r.bilan_id = b.id) AS nb_valeurs
             FROM bilan_sanguin b WHERE ' . $this->ownerWhere($owners, 'b') . ' ORDER BY b.date_bilan DESC, b.id DESC'
        )->fetchAll();
        $this->renderFront('bilans', [
            'pageTitle'   => $this->t['mod_analyses'] . ' - LabTrack',
            'currentPage' => 'bilans',
            'owners'      => $owners,
            'bilans'      => $rows,
            'flash'       => AuthController::pullFlash(),
        ]);
    }

    /** Envoi d'un rapport : GET = formulaire, POST = lecture par l'IA puis page de relecture */
    public function upload(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        $errors = [];
        $ownerKey = (string) ($_POST['owner'] ?? 'u' . $this->me());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->handleUpload($owners, $ownerKey);
            if (empty($errors)) {
                $this->redirect('bilan_review');
            }
        }
        $this->renderFront('bilan_upload', [
            'pageTitle'   => $this->t['bil_upload_title'] . ' - LabTrack',
            'currentPage' => 'bilans',
            'owners'      => $owners,
            'ownerKey'    => $ownerKey,
            'errors'      => $errors,
        ]);
    }

    /** Relecture des valeurs lues (GET) et enregistrement du bilan (POST) */
    public function review(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        $draft = $_SESSION['bilan_draft'] ?? null;
        if (!is_array($draft) || !isset($owners[$draft['owner'] ?? ''])) {
            $this->redirect('bilan_upload');
        }
        $old = $draft['data'];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->checkCsrf()) {
                $errors[] = $this->t['err_csrf'];
            } else {
                [$clean, $errors] = $this->validateReview($_POST);
                $old = $clean;
                if (empty($errors)) {
                    $id = $this->saveBilan($owners[$draft['owner']], $draft, $clean);
                    unset($_SESSION['bilan_draft']);
                    AuthController::setFlash('success', 'bil_msg_saved');
                    $this->redirect('bilan_view', 'id=' . $id);
                }
            }
        }
        $this->renderFront('bilan_review', [
            'pageTitle'   => $this->t['bil_review_title'] . ' - LabTrack',
            'currentPage' => 'bilans',
            'owner'       => $owners[$draft['owner']],
            'old'         => $old,
            'errors'      => $errors,
        ]);
    }

    /** Détail d'un bilan */
    public function view(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        $bilan = $this->findBilan((int) ($_GET['id'] ?? 0), $owners);
        if (!$bilan) {
            AuthController::setFlash('error', 'bil_err_not_found');
            $this->redirect('bilans');
        }
        $stmt = $this->db->prepare('SELECT * FROM resultat_analyse WHERE bilan_id = :id ORDER BY id');
        $stmt->execute(['id' => $bilan['id']]);
        $owner = $bilan['user_id'] !== null ? $owners['u' . $bilan['user_id']] : $owners['m' . $bilan['membre_id']];
        $this->renderFront('bilan_view', [
            'pageTitle'   => $bilan['titre'] . ' - LabTrack',
            'currentPage' => 'bilans',
            'bilan'       => $bilan,
            'results'     => $stmt->fetchAll(),
            'owner'       => $owner,
            'showOwner'   => count($owners) > 1,
            'flash'       => AuthController::pullFlash(),
        ]);
    }

    /** Rapport d'origine (PDF/photo), servi par PHP seulement à son propriétaire */
    public function file(): void
    {
        AuthController::requireAuth();
        $bilan = $this->findBilan((int) ($_GET['id'] ?? 0), $this->ownersFor($this->me()));
        $path = $bilan && $bilan['fichier'] ? __DIR__ . '/../storage/bilans/' . basename($bilan['fichier']) : '';
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        $mime = array_search($bilan['fichier_type'], self::TYPES, true) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="rapport.' . $bilan['fichier_type'] . '"');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    /** POST : supprimer un bilan (et son fichier) */
    public function delete(): void
    {
        AuthController::requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('bilans');
        }
        $bilan = $this->findBilan((int) ($_POST['id'] ?? 0), $this->ownersFor($this->me()));
        if ($bilan && $this->checkCsrf()) {
            $this->db->prepare('DELETE FROM bilan_sanguin WHERE id = :id')->execute(['id' => $bilan['id']]);
            $this->removeFile($bilan['fichier']);
            AuthController::setFlash('success', 'bil_msg_deleted');
        }
        $this->redirect('bilans');
    }

    // ==============================================================
    // ENVOI ET LECTURE
    // ==============================================================

    /** @return string[] erreurs ; si vide, le brouillon est en session */
    private function handleUpload(array $owners, string $ownerKey): array
    {
        if (!$this->checkCsrf()) {
            return [$this->t['err_csrf']];
        }
        if (!isset($owners[$ownerKey])) {
            return [$this->t['bil_err_owner']];
        }
        if (empty($_POST['consent'])) {
            return [$this->t['bil_err_consent']];
        }
        $file = $_FILES['rapport'] ?? null;
        if (!is_array($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return [$this->t['bil_err_file']];
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > self::MAX_SIZE) {
            return [$this->t['bil_err_file_size']];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return [$this->t['bil_err_file']];
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::TYPES[$mime])) {
            return [$this->t['bil_err_file_type']];
        }

        // Un ancien brouillon abandonné est supprimé
        if (!empty($_SESSION['bilan_draft']['file'])) {
            $this->removeFile($_SESSION['bilan_draft']['file']);
        }
        unset($_SESSION['bilan_draft']);

        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $target = __DIR__ . '/../storage/bilans/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return [$this->t['bil_err_file']];
        }
        $result = AiExtractor::extract($target, $mime);
        if (!$result['ok']) {
            $this->removeFile($name);
            return [$this->t[$result['error']] ?? $this->t['bil_err_ai_unreadable']];
        }
        $_SESSION['bilan_draft'] = ['file' => $name, 'type' => self::TYPES[$mime], 'owner' => $ownerKey, 'data' => $result['data']];
        return [];
    }

    // ==============================================================
    // VALIDATION ET ENREGISTREMENT
    // ==============================================================

    /** @return array{0: array, 1: string[]} [données nettoyées, erreurs] */
    private function validateReview(array $post): array
    {
        $errors = [];
        $clean = [
            'titre'       => trim((string) ($post['titre'] ?? '')),
            'date_bilan'  => trim((string) ($post['date_bilan'] ?? '')),
            'laboratoire' => trim((string) ($post['laboratoire'] ?? '')),
            'resultats'   => [],
        ];
        if (mb_strlen($clean['titre']) < 2 || mb_strlen($clean['titre']) > 150) {
            $errors[] = $this->t['bil_err_titre'];
        }
        $d = DateTime::createFromFormat('!Y-m-d', $clean['date_bilan']);
        if (!$d || $d->format('Y-m-d') !== $clean['date_bilan'] || $clean['date_bilan'] > date('Y-m-d')) {
            $errors[] = $this->t['bil_err_date'];
        }
        if (mb_strlen($clean['laboratoire']) > 150) {
            $errors[] = $this->t['bil_err_lab'];
        }

        $rows = is_array($post['r'] ?? null) ? array_slice($post['r'], 0, 80) : [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $row = [];
            foreach (['nom', 'valeur', 'valeur_texte', 'unite', 'ref_min', 'ref_max'] as $k) {
                $row[$k] = is_string($r[$k] ?? null) ? trim($r[$k]) : '';
            }
            if (implode('', $row) === '') {
                continue; // ligne vide ignorée
            }
            foreach (['valeur', 'ref_min', 'ref_max'] as $k) {
                $row[$k] = str_replace(',', '.', $row[$k]);
            }
            $clean['resultats'][] = $row;
            if ($row['nom'] === '' || mb_strlen($row['nom']) > 100 || mb_strlen($row['unite']) > 30 || mb_strlen($row['valeur_texte']) > 50) {
                $errors[] = $this->t['bil_err_row'];
            } elseif ($row['valeur'] === '' && $row['valeur_texte'] === '') {
                $errors[] = sprintf($this->t['bil_err_row_value'], $row['nom']);
            }
            foreach (['valeur', 'ref_min', 'ref_max'] as $k) {
                if ($row[$k] !== '' && (!is_numeric($row[$k]) || abs((float) $row[$k]) >= 1e9)) {
                    $errors[] = sprintf($this->t['bil_err_num'], $row['nom']);
                    break;
                }
            }
            if (is_numeric($row['ref_min']) && is_numeric($row['ref_max']) && (float) $row['ref_min'] > (float) $row['ref_max']) {
                $errors[] = sprintf($this->t['bil_err_range'], $row['nom']);
            }
        }
        if (empty($clean['resultats'])) {
            $errors[] = $this->t['bil_err_no_rows'];
        }
        return [$clean, array_values(array_unique($errors))];
    }

    /** bas / normal / haut par rapport à la norme du rapport ; inconnu s'il n'y a pas de norme */
    public static function statusFor(?float $value, ?float $min, ?float $max): string
    {
        if ($value === null || ($min === null && $max === null)) {
            return 'inconnu';
        }
        if ($min !== null && $value < $min) {
            return 'bas';
        }
        if ($max !== null && $value > $max) {
            return 'haut';
        }
        return 'normal';
    }

    /** Affiche un nombre sans zéros inutiles (4.5000 -> 4.5) */
    public static function fmt(?string $n): string
    {
        if ($n === null || $n === '') {
            return '';
        }
        return rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
    }

    private function saveBilan(array $owner, array $draft, array $data): int
    {
        $toNum = fn(string $v): ?float => $v === '' ? null : (float) $v;
        $lines = [];
        $out = false;
        foreach ($data['resultats'] as $r) {
            $value = $toNum($r['valeur']);
            $status = self::statusFor($value, $toNum($r['ref_min']), $toNum($r['ref_max']));
            $out = $out || $status === 'bas' || $status === 'haut';
            $lines[] = [$r, $value, $status];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO bilan_sanguin (user_id, membre_id, titre, date_bilan, hors_norme, laboratoire, fichier, fichier_type, created_by)
                 VALUES (:u, :m, :titre, :date, :hn, :lab, :f, :ft, :by)'
            )->execute([
                'u' => $owner['user_id'], 'm' => $owner['membre_id'], 'titre' => $data['titre'], 'date' => $data['date_bilan'],
                'hn' => $out ? 1 : 0, 'lab' => $data['laboratoire'] !== '' ? $data['laboratoire'] : null,
                'f' => $draft['file'], 'ft' => $draft['type'], 'by' => $this->me(),
            ]);
            $id = (int) $this->db->lastInsertId();
            $ins = $this->db->prepare(
                'INSERT INTO resultat_analyse (bilan_id, nom, valeur, valeur_texte, unite, ref_min, ref_max, statut)
                 VALUES (:b, :nom, :v, :vt, :u, :min, :max, :s)'
            );
            foreach ($lines as [$r, $value, $status]) {
                $ins->execute([
                    'b' => $id, 'nom' => $r['nom'], 'v' => $value, 'vt' => $r['valeur_texte'] !== '' ? $r['valeur_texte'] : null,
                    'u' => $r['unite'] !== '' ? $r['unite'] : null, 'min' => $toNum($r['ref_min']), 'max' => $toNum($r['ref_max']), 's' => $status,
                ]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $id;
    }

    // ==============================================================
    // PROPRIÉTAIRES (moi + profils à charge que je gère), comme dans RappelController
    // ==============================================================

    public function ownersFor(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT nom FROM utilisateur WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $owners = ['u' . $userId => ['label' => (string) $stmt->fetchColumn(), 'user_id' => $userId, 'membre_id' => null, 'family' => null, 'self' => true]];
        $stmt = $this->db->prepare(
            "SELECT d.id, d.nom, f.nom AS famille FROM famille_membre d
             JOIN famille f ON f.id = d.famille_id
             JOIN famille_membre moi ON moi.famille_id = d.famille_id AND moi.user_id = :u AND moi.role IN ('manager', 'caregiver')
             WHERE d.user_id IS NULL ORDER BY d.nom"
        );
        $stmt->execute(['u' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $owners['m' . $row['id']] = ['label' => $row['nom'], 'user_id' => null, 'membre_id' => (int) $row['id'], 'family' => $row['famille'], 'self' => false];
        }
        return $owners;
    }

    /** Condition SQL "appartient à un propriétaire que je gère" (ids entiers uniquement) */
    private function ownerWhere(array $owners, string $alias = ''): string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        $users = [];
        $membres = [];
        foreach ($owners as $o) {
            $o['user_id'] !== null ? $users[] = (int) $o['user_id'] : $membres[] = (int) $o['membre_id'];
        }
        $parts = [];
        if ($users) {
            $parts[] = $p . 'user_id IN (' . implode(',', $users) . ')';
        }
        if ($membres) {
            $parts[] = $p . 'membre_id IN (' . implode(',', $membres) . ')';
        }
        return $parts ? '(' . implode(' OR ', $parts) . ')' : '0 = 1';
    }

    /** Un bilan, SEULEMENT s'il appartient à quelqu'un que je gère (sinon null) */
    private function findBilan(int $id, array $owners): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM bilan_sanguin WHERE id = :id AND ' . $this->ownerWhere($owners) . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private function me(): int
    {
        return (int) $_SESSION['user_id'];
    }

    private function checkCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function removeFile(?string $name): void
    {
        if ($name) {
            $path = __DIR__ . '/../storage/bilans/' . basename($name);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Tables du module (créées au premier usage, même schéma dans database.sql).
     * bilan_sanguin peut exister depuis docs/family_modules_contract.sql : on ajoute les colonnes du module.
     */
    private function ensureTables(): void
    {
        if (!empty($_SESSION['bilan_tables_v1'])) {
            return;
        }
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS bilan_sanguin (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                membre_id INT NULL,
                titre VARCHAR(150) NOT NULL,
                date_bilan DATE NOT NULL,
                hors_norme TINYINT(1) NOT NULL DEFAULT 0,
                laboratoire VARCHAR(150) NULL,
                fichier VARCHAR(64) NULL,
                fichier_type VARCHAR(4) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_bilan_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_bilan_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS resultat_analyse (
                id INT AUTO_INCREMENT PRIMARY KEY,
                bilan_id INT NOT NULL,
                nom VARCHAR(100) NOT NULL,
                valeur DECIMAL(14,4) NULL,
                valeur_texte VARCHAR(50) NULL,
                unite VARCHAR(30) NULL,
                ref_min DECIMAL(14,4) NULL,
                ref_max DECIMAL(14,4) NULL,
                statut ENUM('normal','bas','haut','inconnu') NOT NULL DEFAULT 'inconnu',
                CONSTRAINT fk_resultat_bilan FOREIGN KEY (bilan_id) REFERENCES bilan_sanguin(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec("ALTER TABLE bilan_sanguin
            ADD COLUMN IF NOT EXISTS laboratoire VARCHAR(150) NULL,
            ADD COLUMN IF NOT EXISTS fichier VARCHAR(64) NULL,
            ADD COLUMN IF NOT EXISTS fichier_type VARCHAR(4) NULL,
            ADD COLUMN IF NOT EXISTS created_by INT NULL,
            ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        $_SESSION['bilan_tables_v1'] = true;
    }
}
