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
require_once __DIR__ . '/../config/Mailer.php';

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

    /** Connexion */
    public function loginPage(): void
    {
        if (self::isAuthenticated()) {
            $this->redirect(self::homeFor());
        }
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->login($_POST);
            if (empty($errors)) {
                $this->redirect(self::homeFor());
            }
        }
        $this->renderFront('login', [
            'pageTitle'   => $this->t['login_title'] . ' - LabTrack',
            'currentPage' => 'login',
            'errors'      => $errors,
            'flash'       => self::pullFlash(), // ex : session fermée par une connexion ailleurs
        ]);
    }

    /** Inscription */
    public function registerPage(): void
    {
        if (self::isAuthenticated()) {
            $this->redirect(self::homeFor());
        }
        $errors = [];
        $old = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->register($_POST);
            $old = [
                'nom'    => $_POST['nom'] ?? '',
                'email'  => $_POST['email'] ?? '',
                'langue' => $_POST['langue'] ?? self::getLang(),
            ];
            if (empty($errors)) {
                $this->redirect('login', 'registered=1');
            }
        }
        $this->renderFront('register', [
            'pageTitle'   => $this->t['register_title'] . ' - LabTrack',
            'currentPage' => 'register',
            'errors'      => $errors,
            'old'         => $old,
        ]);
    }

    /** Déconnexion */
    public function logoutAction(): void
    {
        $this->logout();
        $this->redirect('login');
    }

    /**
     * Mot de passe oublié (étape 1) : l'utilisateur donne son email et reçoit un code.
     * La réponse est la même, que le compte existe ou non (on ne révèle pas
     * quels emails sont inscrits).
     */
    public function forgotPasswordPage(): void
    {
        if (self::isAuthenticated()) {
            $this->redirect(self::homeFor());
        }
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->requestResetCode($_POST);
            if (empty($errors)) {
                self::setFlash('success', 'msg_reset_sent');
                $this->redirect('reset_password');
            }
        }
        $this->renderFront('forgot_password', [
            'pageTitle'   => $this->t['forgot_title'] . ' - LabTrack',
            'currentPage' => 'forgot_password',
            'errors'      => $errors,
            'oldEmail'    => $_POST['email'] ?? ($_SESSION['reset_email'] ?? ''),
        ]);
    }

    /** Mot de passe oublié (étape 2) : code reçu + nouveau mot de passe */
    public function resetPasswordPage(): void
    {
        if (self::isAuthenticated()) {
            $this->redirect(self::homeFor());
        }
        if (empty($_SESSION['reset_email'])) {
            $this->redirect('forgot_password');
        }
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->resetPassword($_POST);
            if (empty($errors)) {
                self::setFlash('success', 'msg_password_reset');
                $this->redirect('login');
            }
        }
        // Compte à rebours affiché : calculé depuis l'heure d'envoi gardée en session,
        // pour se comporter de la même façon que l'email existe ou non.
        $elapsed = time() - (int) ($_SESSION['reset_sent_at'] ?? 0);
        $this->renderFront('reset_password', [
            'pageTitle'   => $this->t['reset_title'] . ' - LabTrack',
            'currentPage' => 'reset_password',
            'errors'      => $errors,
            'flash'       => self::pullFlash(),
            'resetEmail'  => $_SESSION['reset_email'],
            'secondsLeft' => max(0, self::RESET_CODE_MINUTES * 60 - $elapsed),
        ]);
    }

    

    /** Changer la langue puis revenir à la page d'origine */
    public function changeLang(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->setLang($_POST);
        }
        // Liste blanche des pages de retour : pas de redirection libre
        $allowedBack = ['home', 'login', 'register', 'forgot_password', 'reset_password', 'dashboard', 'profile', 'admin', 'admin_users',
            'family', 'family_view', 'family_member', 'family_card', 'urgence', 'rappels', 'rappel_form', 'rdv_form', 'rappels_print'];
        $back = in_array($_POST['back'] ?? '', $allowedBack, true) ? $_POST['back'] : 'home';
        // Paramètres de la page d'origine (famille, onglet, membre, mois...) : chacun revérifié
        parse_str((string) ($_POST['back_query'] ?? ''), $query);
        $rules = ['id' => '/^\d{1,10}$/', 'member' => '/^\d{1,10}$/', 'tab' => '/^[a-z]{1,20}$/', 'owner' => '/^[um]\d{1,10}$/',
                  'm' => '/^\d{4}-\d{2}$/', 't' => '/^[a-f0-9]{32}$/'];
        $safe = [];
        foreach ($rules as $key => $regex) {
            if (isset($query[$key]) && is_string($query[$key]) && preg_match($regex, $query[$key])) {
                $safe[$key] = $query[$key];
            }
        }
        $this->redirect($back, http_build_query($safe));
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
        return self::translationsFor(self::getLang());
    }

    /**
     * Textes d'une langue : fichier commun config/lang/<langue>.php, plus les fichiers
     * propres à chaque module, config/lang/<module>/<langue>.php (ex : family/fr.php).
     * Chaque membre du groupe ajoute ses textes dans son propre fichier : pas de conflit Git.
     */
    public static function translationsFor(string $lang): array
    {
        $lang = in_array($lang, self::LANGUES, true) ? $lang : 'fr';
        $t = require __DIR__ . '/../config/lang/' . $lang . '.php';
        foreach (glob(__DIR__ . '/../config/lang/*/' . $lang . '.php') ?: [] as $file) {
            $t += (array) require $file;
        }
        return $t;
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
    // INSCRIPTION (REGISTER)
    // ==============================================================
    public function register(array $post): array
    {
        $errors = [];

        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            $errors[] = $this->t['err_csrf'];
            return $errors;
        }

        $nom             = trim($post['nom'] ?? '');
        $email           = trim($post['email'] ?? '');
        $password        = $post['password'] ?? '';
        $passwordConfirm = $post['password_confirm'] ?? '';
        $langue          = $post['langue'] ?? self::getLang();

        if ($nom === '' || $email === '' || $password === '') {
            $errors[] = $this->t['err_required'];
        }
        if (!$this->isValidNom($nom)) {
            $errors[] = $this->t['err_nom'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $this->t['err_email'];
        }
        if (strlen($password) < 8) {
            $errors[] = $this->t['err_password_length'];
        }
        if ($password !== $passwordConfirm) {
            $errors[] = $this->t['err_password_match'];
        }
        if (!in_array($langue, self::LANGUES, true)) {
            $errors[] = $this->t['err_langue'];
        }
        if (!empty($errors)) {
            return $errors;
        }

        if ($this->existsByEmail($email)) {
            $errors[] = $this->t['err_email_taken'];
            return $errors;
        }

        // Hachage sécurisé du mot de passe (bcrypt via password_hash)
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Rôle forcé à 'patient' : personne ne peut s'inscrire comme admin
        // depuis le formulaire public. Seul un administrateur peut donner
        // le rôle 'admin' à un compte, depuis le backoffice.
        $user = new UserModel();
        $user->setNom($nom);
        $user->setEmail($email);
        $user->setPassword($hashedPassword);
        $user->setRole('patient');
        $user->setLangue($langue);

        $code = $this->generateUniqueCode();
        if ($code === null) {
            $errors[] = $this->t['err_generic'];
            return $errors;
        }
        $user->setCode($code);

        if (!$this->insertUser($user)) {
            $errors[] = $this->t['err_generic'];
        }
        return $errors; // tableau vide = succès
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
        $_SESSION['avatar']        = $user->getAvatar(); // photo affichée dans les barres du site
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

    /** Espace personnel du patient */
    public function dashboard(): void
    {
        self::requireAuth();
        $user = $this->findById((int) $_SESSION['user_id']);
        if (!$user) {
            $this->logoutAction();
        }
        $this->renderFront('user_space', [
            'pageTitle'   => $this->t['nav_dashboard'] . ' - LabTrack',
            'currentPage' => 'dashboard',
            'user'        => $user,
        ]);
    }

    /** Mon profil : nom, langue, mot de passe */
    public function profile(): void
    {
        self::requireAuth();
        $userId = (int) $_SESSION['user_id'];
        $profileErrors  = [];
        $passwordErrors = [];
        $avatarErrors   = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = $_POST['form'] ?? '';
            if ($form === 'avatar' || $form === 'avatar_remove') {
                $avatarErrors = $form === 'avatar'
                    ? $this->updateAvatar($userId, $_POST, $_FILES)
                    : $this->removeAvatar($userId, $_POST);
                if (empty($avatarErrors)) {
                    self::setFlash('success', $form === 'avatar' ? 'msg_photo_updated' : 'msg_photo_removed');
                    $this->redirect('profile');
                }
            } elseif ($form === 'password') {
                $passwordErrors = $this->changePassword($userId, $_POST);
                if (empty($passwordErrors)) {
                    self::setFlash('success', 'msg_password_updated');
                    $this->redirect('profile');
                }
            } else {
                $profileErrors = $this->updateProfile($userId, $_POST);
                if (empty($profileErrors)) {
                    // la langue a pu changer : on recharge la page dans la nouvelle langue
                    self::setFlash('success', 'msg_profile_updated');
                    $this->redirect('profile');
                }
            }
        }

        $user = $this->findById($userId);
        if (!$user) {
            $this->logoutAction();
        }
        $this->renderFront('profile', [
            'pageTitle'      => $this->t['profile_title'] . ' - LabTrack',
            'currentPage'    => 'profile',
            'user'           => $user,
            'profileErrors'  => $profileErrors,
            'passwordErrors' => $passwordErrors,
            'avatarErrors'   => $avatarErrors,
            'flash'          => self::pullFlash(),
        ]);
    }

    /** Modifier son profil : nom et langue préférée */
    public function updateProfile(int $userId, array $post): array
    {
        $errors = [];

        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            $errors[] = $this->t['err_csrf'];
            return $errors;
        }

        $nom    = trim($post['nom'] ?? '');
        $langue = $post['langue'] ?? '';

        if (!$this->isValidNom($nom)) {
            $errors[] = $this->t['err_nom'];
        }
        if (!in_array($langue, self::LANGUES, true)) {
            $errors[] = $this->t['err_langue'];
        }
        if (!empty($errors)) {
            return $errors;
        }

        $stmt = $this->db->prepare('UPDATE utilisateur SET nom = :nom, langue = :langue WHERE id = :id');
        $stmt->execute(['nom' => $nom, 'langue' => $langue, 'id' => $userId]);

        $_SESSION['nom']    = $nom;
        $_SESSION['langue'] = $langue;
        return [];
    }

    /** Changer son mot de passe (l'ancien est exigé) */
    public function changePassword(int $userId, array $post): array
    {
        $errors = [];

        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            $errors[] = $this->t['err_csrf'];
            return $errors;
        }

        $current = $post['current_password'] ?? '';
        $new     = $post['new_password'] ?? '';
        $confirm = $post['new_password_confirm'] ?? '';

        $user = $this->findById($userId);
        if (!$user || !password_verify($current, $user->getPassword())) {
            $errors[] = $this->t['err_current_password'];
            return $errors;
        }
        if (strlen($new) < 8) {
            $errors[] = $this->t['err_password_length'];
        }
        if ($new !== $confirm) {
            $errors[] = $this->t['err_password_match'];
        }
        if (!empty($errors)) {
            return $errors;
        }

        // Toute modification de mot de passe passe aussi par password_hash()
        $stmt = $this->db->prepare('UPDATE utilisateur SET password = :password WHERE id = :id');
        $stmt->execute(['password' => password_hash($new, PASSWORD_DEFAULT), 'id' => $userId]);

        session_regenerate_id(true);
        return [];
    }

    // ==============================================================
    // PHOTO DE PROFIL
    // ==============================================================
    /** Types acceptés : type MIME réel => extension du fichier enregistré */
    private const AVATAR_TYPES     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const AVATAR_MAX_BYTES = 2097152; // 2 Mo
    private const AVATAR_MAX_SIDE  = 4000;    // pixels

    /**
     * Chemin d'une photo, ou null si le nom n'a pas la forme exacte que l'on génère
     * (32 caractères hexadécimaux + extension) : aucun "../" ni ".php" possible.
     */
    private static function avatarPath(?string $name): ?string
    {
        if ($name === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
            return null;
        }
        return dirname(__DIR__) . '/storage/avatars/' . $name;
    }

    /**
     * Base créée avant la photo de profil : on ajoute la colonne avatar une seule fois,
     * pour que chaque membre de l'équipe n'ait rien à exécuter après un git pull.
     */
    private function ensureAvatarColumn(): void
    {
        if (!$this->db->query("SHOW COLUMNS FROM utilisateur LIKE 'avatar'")->fetch()) {
            $this->db->exec('ALTER TABLE utilisateur ADD COLUMN avatar VARCHAR(64) NULL DEFAULT NULL AFTER locked_until');
        }
    }

    private static function deleteAvatarFile(?string $name): void
    {
        $path = self::avatarPath($name);
        if ($path !== null && is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Ajouter ou remplacer sa photo de profil.
     * Sécurité : jeton CSRF, taille limitée, type lu dans le contenu du fichier
     * (jamais le nom ni le type envoyés par le navigateur), nom aléatoire,
     * image ré-encodée si GD est disponible, dossier storage/ interdit en accès direct.
     */
    public function updateAvatar(int $userId, array $post, array $files): array
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            return [$this->t['err_csrf']];
        }

        $file = $files['avatar'] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return [$this->t['err_photo_upload']];
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return [$this->t['err_photo_size']];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return [$this->t['err_photo_upload']];
        }
        if ($file['size'] <= 0 || $file['size'] > self::AVATAR_MAX_BYTES) {
            return [$this->t['err_photo_size']];
        }

        // Le vrai type, lu dans les premiers octets du fichier, doit être une image lisible
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $info = @getimagesize($file['tmp_name']);
        if (!isset(self::AVATAR_TYPES[$mime]) || $info === false || $info['mime'] !== $mime
            || $info[0] < 1 || $info[1] < 1
            || $info[0] > self::AVATAR_MAX_SIDE || $info[1] > self::AVATAR_MAX_SIDE) {
            return [$this->t['err_photo_type']];
        }

        $dir = dirname(__DIR__) . '/storage/avatars';
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            return [$this->t['err_generic']];
        }
        $name   = bin2hex(random_bytes(16)) . '.' . self::AVATAR_TYPES[$mime];
        $target = $dir . '/' . $name;
        if (!$this->storeAvatar($file['tmp_name'], $target, $mime)) {
            return [$this->t['err_photo_type']];
        }

        $this->ensureAvatarColumn();
        $user = $this->findById($userId);
        $stmt = $this->db->prepare('UPDATE utilisateur SET avatar = :avatar WHERE id = :id');
        $stmt->execute(['avatar' => $name, 'id' => $userId]);
        self::deleteAvatarFile($user ? $user->getAvatar() : null); // l'ancienne photo ne sert plus
        $_SESSION['avatar'] = $name;
        return [];
    }

    /**
     * Enregistre l'image. Avec l'extension GD, elle est redessinée pixel par pixel :
     * tout contenu caché (code, métadonnées EXIF comme la position GPS) disparaît.
     */
    private function storeAvatar(string $tmp, string $target, string $mime): bool
    {
        $encoders = ['image/jpeg' => 'imagejpeg', 'image/png' => 'imagepng', 'image/webp' => 'imagewebp'];
        if (function_exists('imagecreatefromstring') && function_exists($encoders[$mime])) {
            $image = @imagecreatefromstring((string) file_get_contents($tmp));
            if ($image === false) {
                return false;
            }
            if ($mime !== 'image/jpeg') {
                imagesavealpha($image, true); // garde la transparence
            }
            $ok = $encoders[$mime]($image, $target);
            imagedestroy($image);
            return $ok;
        }
        return move_uploaded_file($tmp, $target);
    }

    /** Retirer sa photo (retour aux initiales) */
    public function removeAvatar(int $userId, array $post): array
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            return [$this->t['err_csrf']];
        }
        $this->ensureAvatarColumn();
        $user = $this->findById($userId);
        $stmt = $this->db->prepare('UPDATE utilisateur SET avatar = NULL WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        self::deleteAvatarFile($user ? $user->getAvatar() : null);
        $_SESSION['avatar'] = null;
        return [];
    }

    /**
     * Page "avatar" : envoie la photo d'un compte.
     * Comme sur un réseau social, la photo de profil est visible par tous les
     * membres connectés ; un visiteur non connecté est renvoyé vers la connexion.
     */
    public function avatar(): void
    {
        self::requireAuth();
        $id   = isset($_GET['id']) ? (int) $_GET['id'] : (int) $_SESSION['user_id'];
        $user = $this->findById($id);
        $path = self::avatarPath($user ? $user->getAvatar() : null);
        if ($path === null || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        header('Content-Type: ' . array_search($ext, self::AVATAR_TYPES, true));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="avatar.' . $ext . '"');
        header('Cache-Control: private, max-age=86400');
        header("Content-Security-Policy: default-src 'none'; sandbox"); // une image, rien d'autre
        readfile($path);
        exit;
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
        $target = $this->findById($targetId);
        if (!$target) {
            self::setFlash('error', 'err_user_not_found');
            return false;
        }

        $stmt = $this->db->prepare('DELETE FROM utilisateur WHERE id = :id');
        $stmt->execute(['id' => $targetId]);
        self::deleteAvatarFile($target->getAvatar()); // sa photo est supprimée avec le compte
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
    // MOT DE PASSE OUBLIÉ (code à 6 chiffres envoyé par email, valable 5 minutes)
    // ==============================================================
    /** Durée de validité du code, essais permis et délai avant un nouvel envoi */
    public const RESET_CODE_MINUTES    = 5;
    private const RESET_MAX_ATTEMPTS   = 5;
    private const RESET_RESEND_SECONDS = 60;

    /**
     * Table des codes, créée automatiquement si elle n'existe pas encore
     * (comme la colonne avatar) : rien à importer à la main dans phpMyAdmin.
     * Une seule ligne par compte : un nouveau code remplace l'ancien.
     */
    private function ensureResetTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS password_reset (
                user_id    INT PRIMARY KEY,
                code_hash  VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** Étape 1 : génère un code, l'enregistre haché et l'envoie par email */
    public function requestResetCode(array $post): array
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            return [$this->t['err_csrf']];
        }
        $email = trim($post['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$this->t['err_email']];
        }

        $this->ensureResetTable();
        $user = $this->findByEmail($email);
        if ($user) {
            // Anti-spam : pas plus d'un email par minute pour le même compte
            $stmt = $this->db->prepare(
                'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM password_reset WHERE user_id = :id'
            );
            $stmt->execute(['id' => $user->getId()]);
            $age = $stmt->fetchColumn();
            if ($age !== false && (int) $age < self::RESET_RESEND_SECONDS) {
                return [sprintf($this->t['err_reset_wait'], self::RESET_RESEND_SECONDS - (int) $age)];
            }

            // random_int : générateur cryptographique. Le code est stocké HACHÉ
            // (comme un mot de passe) : même avec la base, on ne peut pas le lire.
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare(
                'REPLACE INTO password_reset (user_id, code_hash, expires_at, attempts, created_at)
                 VALUES (:id, :hash, NOW() + INTERVAL ' . self::RESET_CODE_MINUTES . ' MINUTE, 0, NOW())'
            );
            $stmt->execute(['id' => $user->getId(), 'hash' => password_hash($code, PASSWORD_DEFAULT)]);

            if (!$this->sendResetEmail($user, $code)) {
                $this->db->prepare('DELETE FROM password_reset WHERE user_id = :id')->execute(['id' => $user->getId()]);
                return [$this->t['err_mail_send']];
            }
        }

        $_SESSION['reset_email']   = $email;
        $_SESSION['reset_sent_at'] = time();
        return [];
    }

    /** Email écrit dans la langue préférée du compte */
    private function sendResetEmail(UserModel $user, string $code): bool
    {
        $file = __DIR__ . '/../config/lang/' . $user->getLangue() . '.php';
        $t = in_array($user->getLangue(), self::LANGUES, true) && is_file($file) ? require $file : $this->t;
        $body = sprintf($t['reset_mail_body'], $user->getNom(), $code, self::RESET_CODE_MINUTES);
        return (new Mailer())->send($user->getEmail(), $t['reset_mail_subject'], $body);
    }

    /** Étape 2 : vérifie le code (5 minutes, 5 essais) puis change le mot de passe */
    public function resetPassword(array $post): array
    {
        if (!$this->verifyCsrfToken($post['csrf_token'] ?? null)) {
            return [$this->t['err_csrf']];
        }
        $code    = trim($post['code'] ?? '');
        $new     = $post['new_password'] ?? '';
        $confirm = $post['new_password_confirm'] ?? '';

        if (!preg_match('/^\d{6}$/', $code)) {
            return [$this->t['err_reset_code']];
        }
        $errors = [];
        if (strlen($new) < 8) {
            $errors[] = $this->t['err_password_length'];
        }
        if ($new !== $confirm) {
            $errors[] = $this->t['err_password_match'];
        }
        if (!empty($errors)) {
            return $errors;
        }

        $this->ensureResetTable();
        $user = $this->findByEmail((string) ($_SESSION['reset_email'] ?? ''));
        $row  = false;
        if ($user) {
            // NOW() de MySQL : la même horloge que celle qui a fixé l'expiration
            $stmt = $this->db->prepare(
                'SELECT code_hash, attempts, expires_at > NOW() AS still_valid
                 FROM password_reset WHERE user_id = :id'
            );
            $stmt->execute(['id' => $user->getId()]);
            $row = $stmt->fetch();
        }
        if (!$user || !$row) {
            return [$this->t['err_reset_code']];
        }

        $delete = $this->db->prepare('DELETE FROM password_reset WHERE user_id = :id');
        if (!(int) $row['still_valid']) {
            $delete->execute(['id' => $user->getId()]);
            return [$this->t['err_reset_expired']];
        }
        if (!password_verify($code, $row['code_hash'])) {
            // Au 5e mauvais code, le code est détruit : il faut en demander un autre
            if ((int) $row['attempts'] + 1 >= self::RESET_MAX_ATTEMPTS) {
                $delete->execute(['id' => $user->getId()]);
                return [$this->t['err_reset_attempts']];
            }
            $this->db->prepare('UPDATE password_reset SET attempts = attempts + 1 WHERE user_id = :id')
                ->execute(['id' => $user->getId()]);
            return [$this->t['err_reset_code']];
        }

        // Code correct : nouveau mot de passe haché, compte débloqué, et toute
        // session ouverte ailleurs est fermée (session_token remis à NULL).
        $stmt = $this->db->prepare(
            'UPDATE utilisateur SET password = :password, failed_attempts = 0,
                    locked_until = NULL, session_token = NULL WHERE id = :id'
        );
        $stmt->execute(['password' => password_hash($new, PASSWORD_DEFAULT), 'id' => $user->getId()]);
        $delete->execute(['id' => $user->getId()]); // un code ne sert qu'une seule fois

        unset($_SESSION['reset_email'], $_SESSION['reset_sent_at']);
        session_regenerate_id(true);
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
