<?php
/**
 * controllers/AdminController.php
 * BACKOFFICE (rôle admin uniquement) - template Registrar.
 * Comme dans l'atelier (DashboardController, TablesController...), chaque
 * méthode prépare les données puis appelle render(), qui place la page
 * dans views/back/layout.php.
 */
class AdminController extends Controller
{
    protected string $page = 'admin';
    protected string $title = 'LabTrack Admin';

    /** La logique métier des comptes reste dans AuthController */
    private AuthController $auth;
    private array $t;

    public function __construct()
    {
        // Le rôle est vérifié côté serveur pour CHAQUE page et action du backoffice
        AuthController::requireRole('admin');
        $this->auth = new AuthController();
        $this->t = AuthController::loadTranslations();
    }

    /** Tableau de bord : statistiques et derniers inscrits */
    public function dashboard(): void
    {
        $this->render('dashboard', [
            'title'         => $this->t['admin_dashboard'] . ' · LabTrack Admin',
            'heading'       => $this->t['admin_dashboard'],
            'currentPage'   => 'admin',
            'admin'         => $this->auth->findById((int) $_SESSION['user_id']),
            'totalUsers'    => $this->auth->countAllUsers(),
            'totalPatients' => $this->auth->countByRole('patient'),
            'newUsers'      => $this->auth->countSince(date('Y-m-d H:i:s', strtotime('-30 days'))),
            'totalAdmins'   => $this->auth->countByRole('admin'),
            'byLangue'      => [
                'fr' => $this->auth->countByLangue('fr'),
                'ar' => $this->auth->countByLangue('ar'),
                'en' => $this->auth->countByLangue('en'),
            ],
            'recentUsers'   => $this->auth->getRecentUsers(5),
            'flash'         => AuthController::pullFlash(),
        ]);
    }

    /** Gestion des utilisateurs : recherche, filtre, rôle, suppression */
    public function users(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $role  = (string) ($_GET['role'] ?? '');

        $this->render('users', [
            'title'       => $this->t['all_users'] . ' · LabTrack Admin',
            'heading'     => $this->t['all_users'],
            'currentPage' => 'admin_users',
            'admin'       => $this->auth->findById((int) $_SESSION['user_id']),
            'users'       => $this->auth->searchUsers($query, $role),
            'query'       => $query,
            'roleFilter'  => in_array($role, AuthController::ROLES, true) ? $role : '',
            'flash'       => AuthController::pullFlash(),
        ], 'users_scripts'); // scripts propres à cette page (confirmation avant suppression)
    }

    /**
     * CRUD - Ajout (sans ?id=) et consultation / modification (avec ?id=) d'un compte.
     * GET affiche le formulaire, POST l'enregistre.
     */
    public function userForm(): void
    {
        $id      = (int) ($_GET['id'] ?? 0);
        $editing = $id > 0;
        $target  = $editing ? $this->auth->findById($id) : null;

        if ($editing && !$target) {
            AuthController::setFlash('error', 'err_user_not_found');
            $this->redirect('admin_users');
        }

        $errors = [];
        $old = $target
            ? ['nom' => $target->getNom(), 'email' => $target->getEmail(), 'role' => $target->getRole(), 'langue' => $target->getLangue()]
            : ['nom' => '', 'email' => '', 'role' => 'patient', 'langue' => AuthController::getLang()];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $editing
                ? $this->auth->adminUpdateUser($id, $_POST)
                : $this->auth->adminCreateUser($_POST);

            if (empty($errors)) {
                AuthController::setFlash('success', $editing ? 'msg_user_updated' : 'msg_user_created');
                $this->redirect('admin_users');
            }
            // On réaffiche ce que l'admin a saisi (sauf les mots de passe)
            $old = [
                'nom'    => trim((string) ($_POST['nom'] ?? '')),
                'email'  => trim((string) ($_POST['email'] ?? '')),
                'role'   => (string) ($_POST['role'] ?? $old['role']),
                'langue' => (string) ($_POST['langue'] ?? $old['langue']),
            ];
        }

        $heading = $editing ? $this->t['edit_user'] : $this->t['add_user'];
        $this->render('user_form', [
            'title'       => $heading . ' · LabTrack Admin',
            'heading'     => $heading,
            'currentPage' => 'admin_users',
            'admin'       => $this->auth->findById((int) $_SESSION['user_id']),
            'editing'     => $editing,
            'target'      => $target,
            'isSelf'      => $editing && $id === (int) $_SESSION['user_id'],
            'old'         => $old,
            'errors'      => $errors,
        ], 'user_form_scripts');
    }

    /** POST : changer le rôle d'un compte */
    public function updateRole(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->auth->adminUpdateRole($_POST);
        }
        $this->redirect('admin_users');
    }

    /** POST : supprimer un compte */
    public function deleteUser(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->auth->adminDeleteUser($_POST);
        }
        $this->redirect('admin_users');
    }
}
