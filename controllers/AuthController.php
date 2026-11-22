<?php
/**
 * controllers/AuthController.php
 * Module "Gestion des utilisateurs" de LabTrack : comptes, rôles, langue.
 * Contient TOUTE la logique métier : requêtes SQL préparées, hachage des
 * mots de passe, validation, sessions, CSRF, rôles et langue.
 * Les méthodes "pages" (home, loginPage, ...) sont appelées par config/routes.php.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/UserModel.php';

class AuthController extends Controller
{
    /** Rôles LabTrack : patient (frontoffice) et admin (backoffice) */
    public const ROLES = ['patient', 'admin'];

    /** Langues proposées par LabTrack */
    public const LANGUES = ['fr', 'ar', 'en'];

    private PDO $db;

    /** Textes de l'interface dans la langue courante */
    private array $t;

    public function __construct()
    {
        $this->db = Database::getConnexion();
        $this->t  = self::loadTranslations();
    }

    // ==============================================================
    // PAGES DU FRONTOFFICE (appelées par le routeur)
    // ==============================================================

    /** Page d'accueil publique */
    public function home(): void
    {
        $this->renderFront('home', [
            'pageTitle'   => 'LabTrack - ' . $this->t['tagline'],
            'currentPage' => 'home',
        ]);
    }

    /** Changer la langue puis revenir à la page d'origine */
    public function changeLang(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->setLang($_POST);
        }
        // Liste blanche des pages de retour : pas de redirection libre
        $allowedBack = ['home', 'login', 'register', 'dashboard', 'profile', 'admin', 'admin_users'];
        $back = in_array($_POST['back'] ?? '', $allowedBack, true) ? $_POST['back'] : 'home';
        $this->redirect($back);
    }

    // ==============================================================
    // LANGUE (arabe, français, anglais)
    // ==============================================================

    /** Langue courante : celle de la session, sinon le français */
    public static function getLang(): string
    {
        $lang = $_SESSION['langue'] ?? 'fr';
        return in_array($lang, self::LANGUES, true) ? $lang : 'fr';
    }

    /** L'arabe s'écrit de droite à gauche */
    public static function isRtl(): bool
    {
        return self::getLang() === 'ar';
    }

    /** Charge le fichier de traduction de la langue courante */
    public static function loadTranslations(): array
    {
        return require __DIR__ . '/../config/lang/' . self::getLang() . '.php';
    }

    /**
     * Change la langue de l'interface.
     * Si l'utilisateur est connecté, sa préférence est aussi enregistrée en base.
     */
    private function setLang(array $post): void
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            return;
        }
        $lang = $post['langue'] ?? '';
        if (!in_array($lang, self::LANGUES, true)) {
            return;
        }
        $_SESSION['langue'] = $lang;

        if (self::isAuthenticated()) {
            $stmt = $this->db->prepare('UPDATE utilisateur SET langue = :langue WHERE id = :id');
            $stmt->execute(['langue' => $lang, 'id' => (int) $_SESSION['user_id']]);
        }
    }

    // ==============================================================
    // ÉTAT DE LA SESSION
    // ==============================================================
    public static function isAuthenticated(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /** Page d'arrivée selon le rôle : backoffice pour l'admin, sinon espace personnel */
    public static function homeFor(): string
    {
        return ($_SESSION['role'] ?? '') === 'admin' ? 'admin' : 'dashboard';
    }

    /**
     * Message ponctuel affiché une seule fois (après une redirection).
     * On stocke la CLÉ de traduction (ex : 'msg_role_updated') et non le texte,
     * pour que le message s'affiche dans la langue de la page suivante.
     */
    public static function setFlash(string $type, string $messageKey): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $messageKey];
    }

    public static function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    // ==============================================================
    // ACCÈS AUX DONNÉES (requêtes préparées - protection injection SQL)
    // ==============================================================
    private function existsByEmail(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM utilisateur WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return (bool) $stmt->fetch();
    }

    private function insertUser(UserModel $user): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO utilisateur (nom, email, code, password, role, langue)
             VALUES (:nom, :email, :code, :password, :role, :langue)'
        );
        return $stmt->execute([
            'nom'      => $user->getNom(),
            'email'    => $user->getEmail(),
            'code'     => $user->getCode(),
            'password' => $user->getPassword(),
            'role'     => $user->getRole(),
            'langue'   => $user->getLangue(),
        ]);
    }

    private function findByEmail(string $email): ?UserModel
    {
        $stmt = $this->db->prepare('SELECT * FROM utilisateur WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ? UserModel::fromArray($row) : null;
    }

    public function findById(int $id): ?UserModel
    {
        $stmt = $this->db->prepare('SELECT * FROM utilisateur WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? UserModel::fromArray($row) : null;
    }

    /** Validation commune du nom (lettres latines ou arabes, espaces, ' et -) */
    private function isValidNom(string $nom): bool
    {
        return (bool) preg_match("/^[\p{L}][\p{L}\s'\-]{1,99}$/u", $nom);
    }

    /**
     * Code personnel à 4 chiffres (0000 à 9999), tiré au hasard et unique.
     * random_int() est un générateur cryptographique : le code n'est pas devinable
     * à partir de l'id. La colonne code est aussi UNIQUE en base (double sécurité).
     */
    private function generateUniqueCode(): ?string
    {
        $stmt = $this->db->prepare('SELECT id FROM utilisateur WHERE code = :code LIMIT 1');
        for ($try = 0; $try < 100; $try++) {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt->execute(['code' => $code]);
            if (!$stmt->fetch()) {
                return $code;
            }
        }
        return null; // presque tous les codes sont pris (10 000 possibles)
    }

    // ==============================================================
    // CONNEXION (LOGIN)
    // ==============================================================
    public function login(array $post): array
    {
        $errors = [];

        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            $errors[] = $this->t['err_csrf'];
            return $errors;
        }

        $email    = trim($post['email'] ?? '');
        $password = $post['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = $this->t['err_login_empty'];
            return $errors;
        }

        $user = $this->findByEmail($email);

        // ANTI FORCE BRUTE : compte bloqué temporairement après trop d'échecs.
        // Même le bon mot de passe est refusé tant que le blocage dure.
        if ($user) {
            $remaining = $this->lockRemainingSeconds($user->getId());
            if ($remaining > 0) {
                $errors[] = sprintf($this->t['err_login_locked'], (int) ceil($remaining / 60));
                return $errors;
            }
        }

        // Message volontairement générique (on ne révèle jamais si l'email existe)
        if (!$user || !password_verify($password, $user->getPassword())) {
            if ($user) {
                $this->recordFailedLogin($user->getId());
            }
            $errors[] = $this->t['err_login'];
            return $errors;
        }

        // Nouvel identifiant de session : protection contre la fixation de session
        session_regenerate_id(true);

        // UNE SEULE CONNEXION À LA FOIS : un nouveau jeton remplace l'ancien en base.
        // La session ouverte ailleurs garde l'ancien jeton et sera fermée à sa
        // prochaine requête par enforceSingleSession().
        // La connexion réussie remet aussi le compteur d'échecs à zéro.
        $sessionToken = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            'UPDATE utilisateur SET session_token = :token, failed_attempts = 0, locked_until = NULL WHERE id = :id'
        );
        $stmt->execute(['token' => $sessionToken, 'id' => $user->getId()]);

        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['user_id']       = $user->getId();
        $_SESSION['nom']           = $user->getNom();
        $_SESSION['email']         = $user->getEmail();
        $_SESSION['role']          = $user->getRole();
        $_SESSION['langue']        = $user->getLangue(); // l'interface passe dans sa langue
        $_SESSION['last_activity'] = time();
        return []; // succès
    }

    /** Nombre d'échecs de connexion avant blocage, et durée du blocage */
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCK_MINUTES       = 15;

    /** Secondes restantes avant la fin du blocage du compte (0 = pas bloqué) */
    private function lockRemainingSeconds(int $userId): int
    {
        // NOW() de MySQL : la même horloge que celle qui a posé le blocage
        $stmt = $this->db->prepare(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) FROM utilisateur WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        return (int) $stmt->fetchColumn(); // NULL (jamais bloqué) devient 0
    }

    /**
     * Compte un échec de connexion. Au 5e échec, le compte est bloqué
     * 15 minutes et le compteur repart à zéro.
     * (MySQL évalue les SET de gauche à droite : locked_until est calculé
     * avec l'ancienne valeur de failed_attempts.)
     */
    private function recordFailedLogin(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE utilisateur SET
                locked_until    = IF(failed_attempts + 1 >= :max1, NOW() + INTERVAL ' . self::LOCK_MINUTES . ' MINUTE, NULL),
                failed_attempts = IF(failed_attempts + 1 >= :max2, 0, failed_attempts + 1)
             WHERE id = :id'
        );
        $stmt->execute([
            'max1' => self::MAX_LOGIN_ATTEMPTS,
            'max2' => self::MAX_LOGIN_ATTEMPTS,
            'id'   => $userId,
        ]);
    }

    // ==============================================================
    // DÉCONNEXION (LOGOUT)
    // ==============================================================
    public function logout(): void
    {
        // On libère le jeton en base, seulement s'il est encore le nôtre
        // (si une autre connexion l'a remplacé, on ne la ferme pas).
        if (self::isAuthenticated() && !empty($_SESSION['session_token'])) {
            $stmt = $this->db->prepare(
                'UPDATE utilisateur SET session_token = NULL WHERE id = :id AND session_token = :token'
            );
            $stmt->execute(['id' => (int) $_SESSION['user_id'], 'token' => $_SESSION['session_token']]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    // ==============================================================
    // ESPACE UTILISATEUR (frontoffice : patient)
    // ==============================================================

    /**
     * UNE SEULE CONNEXION À LA FOIS (appelée par index.php à chaque requête).
     * Si le jeton de cette session n'est plus celui enregistré en base, c'est que
     * le compte s'est connecté ailleurs (ou a été supprimé) : on ferme CETTE session.
     */
    public static function enforceSingleSession(): void
    {
        if (!self::isAuthenticated()) {
            return;
        }
        $stmt = Database::getConnexion()->prepare('SELECT session_token FROM utilisateur WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $dbToken = $stmt->fetchColumn();          // false si le compte n'existe plus
        $myToken = (string) ($_SESSION['session_token'] ?? '');

        if (is_string($dbToken) && $myToken !== '' && hash_equals($dbToken, $myToken)) {
            return; // c'est bien la connexion active
        }

        $lang = self::getLang();
        $_SESSION = [];                 // on oublie l'utilisateur...
        session_regenerate_id(true);    // ...et on change d'identifiant de session
        $_SESSION['langue'] = $lang;    // on garde seulement la langue de l'interface
        self::setFlash('warning', $dbToken === false ? 'msg_account_gone' : 'msg_session_replaced');
    }

    /** Redirige et arrête l'exécution si l'utilisateur n'est pas connecté */
    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            header('Location: index.php?page=login');
            exit;
        }
    }

    // ==============================================================
    // CONTRÔLE DES RÔLES
    // ==============================================================
    public static function hasRole(string $role): bool
    {
        return self::isAuthenticated() && ($_SESSION['role'] ?? null) === $role;
    }

    /** Redirige et arrête l'exécution si l'utilisateur n'a pas le rôle exigé */
    public static function requireRole(string $role): void
    {
        self::requireAuth();
        if (($_SESSION['role'] ?? null) !== $role) {
            http_response_code(403);
            die('Access denied: you do not have the required permissions. / Accès refusé.');
        }
    }

    // ==============================================================
    // DONNÉES DU BACKOFFICE ADMIN
    // ==============================================================

    /**
     * Liste des comptes, avec recherche (nom ou email) et filtre par rôle.
     * @return UserModel[]
     */
    public function searchUsers(string $query = '', string $role = ''): array
    {
        $sql = 'SELECT * FROM utilisateur WHERE 1 = 1';
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (nom LIKE :q1 OR email LIKE :q2)';
            $params['q1'] = '%' . $query . '%';
            $params['q2'] = '%' . $query . '%';
        }
        if (in_array($role, self::ROLES, true)) {
            $sql .= ' AND role = :role';
            $params['role'] = $role;
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(fn(array $row) => UserModel::fromArray($row), $stmt->fetchAll());
    }

    /** @return UserModel[] les derniers comptes créés */
    public function getRecentUsers(int $limit = 5): array
    {
        $stmt = $this->db->prepare('SELECT * FROM utilisateur ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(fn(array $row) => UserModel::fromArray($row), $stmt->fetchAll());
    }

    public function countByRole(string $role): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM utilisateur WHERE role = :role');
        $stmt->execute(['role' => $role]);
        return (int) $stmt->fetchColumn();
    }

    /** Nombre de comptes créés depuis une date (ex : 30 derniers jours) */
    public function countSince(string $since): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM utilisateur WHERE created_at >= :since');
        $stmt->execute(['since' => $since]);
        return (int) $stmt->fetchColumn();
    }

    public function countByLangue(string $langue): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM utilisateur WHERE langue = :langue');
        $stmt->execute(['langue' => $langue]);
        return (int) $stmt->fetchColumn();
    }

    public function countAllUsers(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM utilisateur')->fetchColumn();
    }

    /** L'admin change le rôle d'un compte (patient <-> admin) */
    public function adminUpdateRole(array $post): bool
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            self::setFlash('error', 'err_csrf');
            return false;
        }

        $targetId = (int) ($post['user_id'] ?? 0);
        $role     = $post['role'] ?? '';

        if (!in_array($role, self::ROLES, true) || !$this->findById($targetId)) {
            self::setFlash('error', 'err_generic');
            return false;
        }
        // Un admin ne modifie pas son propre rôle (il pourrait se bloquer)
        if ($targetId === (int) $_SESSION['user_id']) {
            self::setFlash('error', 'err_self_action');
            return false;
        }

        $stmt = $this->db->prepare('UPDATE utilisateur SET role = :role WHERE id = :id');
        $stmt->execute(['role' => $role, 'id' => $targetId]);
        self::setFlash('success', 'msg_role_updated');
        return true;
    }

    /** L'admin supprime un compte */
    public function adminDeleteUser(array $post): bool
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            self::setFlash('error', 'err_csrf');
            return false;
        }

        $targetId = (int) ($post['user_id'] ?? 0);

        if ($targetId === (int) $_SESSION['user_id']) {
            self::setFlash('error', 'err_self_action');
            return false;
        }
        // On vérifie que le compte existe avant d'annoncer sa suppression
        if (!$this->findById($targetId)) {
            self::setFlash('error', 'err_user_not_found');
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM utilisateur WHERE id = :id');
        $stmt->execute(['id' => $targetId]);
        self::setFlash('success', 'msg_user_deleted');
        return true;
    }

    /** L'email est-il déjà utilisé par un AUTRE compte que $exceptId ? */
    private function emailTakenByOther(string $email, int $exceptId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM utilisateur WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute(['email' => $email, 'id' => $exceptId]);
        return (bool) $stmt->fetch();
    }

    /** Validation commune des formulaires de compte du backoffice (ajout et modification) */
    private function validateAccountFields(string $nom, string $email, string $role, string $langue): array
    {
        $errors = [];
        if ($nom === '' || $email === '') {
            $errors[] = $this->t['err_required'];
        }
        if (!$this->isValidNom($nom)) {
            $errors[] = $this->t['err_nom'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->t['err_email'];
        }
        if (!in_array($role, self::ROLES, true)) {
            $errors[] = $this->t['err_role'];
        }
        if (!in_array($langue, self::LANGUES, true)) {
            $errors[] = $this->t['err_langue'];
        }
        return $errors;
    }

    /**
     * CRUD - Ajout : l'admin crée un compte et choisit son rôle.
     * @return string[] erreurs (tableau vide = succès)
     */
    public function adminCreateUser(array $post): array
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            return [$this->t['err_csrf']];
        }

        $nom             = trim($post['nom'] ?? '');
        $email           = trim($post['email'] ?? '');
        $role            = (string) ($post['role'] ?? '');
        $langue          = (string) ($post['langue'] ?? '');
        $password        = (string) ($post['password'] ?? '');
        $passwordConfirm = (string) ($post['password_confirm'] ?? '');

        $errors = $this->validateAccountFields($nom, $email, $role, $langue);
        if (strlen($password) < 8) {
            $errors[] = $this->t['err_password_length'];
        }
        if ($password !== $passwordConfirm) {
            $errors[] = $this->t['err_password_match'];
        }
        if (!empty($errors)) {
            return $errors;
        }
        if ($this->existsByEmail($email)) {
            return [$this->t['err_email_taken']];
        }

        $user = new UserModel();
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setPassword(password_hash($password, PASSWORD_DEFAULT)); // jamais en clair
        $user->setRole($role);
        $user->setLangue($langue);

        $code = $this->generateUniqueCode();
        if ($code === null) {
            return [$this->t['err_generic']];
        }
        $user->setCode($code);

        return $this->insertUser($user) ? [] : [$this->t['err_generic']];
    }

    /**
     * CRUD - Modification : nom, email, langue, rôle et (optionnel) nouveau mot de passe.
     * Un admin peut modifier son propre compte, mais pas son propre rôle.
     * @return string[] erreurs (tableau vide = succès)
     */
    public function adminUpdateUser(int $targetId, array $post): array
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            return [$this->t['err_csrf']];
        }

        $target = $this->findById($targetId);
        if (!$target) {
            return [$this->t['err_user_not_found']];
        }
        $isSelf = $targetId === (int) $_SESSION['user_id'];

        $nom             = trim($post['nom'] ?? '');
        $email           = trim($post['email'] ?? '');
        $langue          = (string) ($post['langue'] ?? '');
        // Son propre rôle ne change jamais (l'admin pourrait se bloquer hors du backoffice)
        $role            = $isSelf ? $target->getRole() : (string) ($post['role'] ?? '');
        $password        = (string) ($post['password'] ?? '');
        $passwordConfirm = (string) ($post['password_confirm'] ?? '');

        $errors = $this->validateAccountFields($nom, $email, $role, $langue);
        if ($password !== '') {
            if (strlen($password) < 8) {
                $errors[] = $this->t['err_password_length'];
            }
            if ($password !== $passwordConfirm) {
                $errors[] = $this->t['err_password_match'];
            }
        }
        if (!empty($errors)) {
            return $errors;
        }
        if ($this->emailTakenByOther($email, $targetId)) {
            return [$this->t['err_email_taken']];
        }

        $stmt = $this->db->prepare(
            'UPDATE utilisateur SET nom = :nom, email = :email, role = :role, langue = :langue WHERE id = :id'
        );
        $stmt->execute(['nom' => $nom, 'email' => $email, 'role' => $role, 'langue' => $langue, 'id' => $targetId]);

        if ($password !== '') {
            $stmt = $this->db->prepare('UPDATE utilisateur SET password = :password WHERE id = :id');
            $stmt->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $targetId]);
        }

        // Si l'admin modifie son propre compte, la session suit
        if ($isSelf) {
            $_SESSION['nom']    = $nom;
            $_SESSION['email']  = $email;
            $_SESSION['langue'] = $langue;
        }
        return [];
    }

    // ==============================================================
    // CSRF
    // ==============================================================
    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    private function verifyCsrfToken(?string $token): bool
    {
        if (!$token || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
