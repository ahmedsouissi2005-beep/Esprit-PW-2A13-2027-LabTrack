<?php
/**
 * controllers/FamilyController.php
 * Module "Espace famille" de LabTrack (Ahmed).
 *
 * Une famille = un gestionnaire + des membres :
 *  - des comptes LabTrack, ajoutés UNIQUEMENT par invitation avec leur nom + leur
 *    code privé (#0427). L'invité accepte ou refuse, puis choisit ce qu'il partage ;
 *  - des profils à charge (enfant, grand-parent sans compte) créés par le gestionnaire.
 *
 * Sécurité : chaque page vérifie que le compte appartient à la famille et qu'il a le
 * bon rôle (gestionnaire, aidant, membre) ; tentatives de code limitées (comme la
 * connexion) ; invitation valable 48 h ; requêtes préparées ; CSRF sur chaque POST.
 *
 * Données des autres modules (rendez-vous, rappels, analyses, campagnes) : lues
 * seulement si leurs tables existent, et seulement si le membre a accepté de les
 * partager (voir docs/FAMILY_SPACE.md).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Mailer.php';
require_once __DIR__ . '/../config/QrCode.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/FamilleModel.php';
require_once __DIR__ . '/../models/MembreModel.php';

class FamilyController extends Controller
{
    /** Rôles dans une famille */
    public const ROLES = ['manager', 'caregiver', 'member'];

    /** Lien de parenté avec le gestionnaire */
    public const RELATIONS = ['pere', 'mere', 'fils', 'fille', 'frere', 'soeur', 'grand_pere', 'grand_mere', 'conjoint', 'autre'];

    /** Types de données qu'un membre peut choisir de partager */
    public const SHARES = ['analyses', 'dossier', 'rappels', 'rdv'];

    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    /** Une invitation sans réponse expire après 48 heures */
    public const INVITE_HOURS = 48;

    /** 5 codes faux en 15 minutes : l'invitation est bloquée 15 minutes (comme la connexion) */
    private const TAG_MAX_ATTEMPTS = 5;
    private const TAG_LOCK_MINUTES = 15;

    private const MAX_MEMBERS = 15;
    private const TEXT_MAX = 500;
    private const MESSAGE_MAX = 1000;

    /** Couleur de chaque membre dans le calendrier et les statistiques */
    public const COLORS = ['#2F8F5B', '#1E3A5F', '#C65D3B', '#8E5CB5', '#D49B1F', '#1F8A9E', '#B5487A', '#5C6F2E'];

    /** Même règle que le nom d'un compte (lettres latines ou arabes, espaces, ' et -) */
    private const NOM_REGEX = "/^[\p{L}][\p{L}\s'\-]{1,99}$/u";
    private const FAMILLE_REGEX = "/^[\p{L}\p{N}][\p{L}\p{N}\s'\-]{1,59}$/u";

    private PDO $db;
    private array $t;
    private array $tableCache = [];

    public function __construct()
    {
        $this->db = Database::getConnexion();
        $this->t  = AuthController::loadTranslations();
        $this->ensureTables();
    }

    // ==============================================================
    // PAGES DU FRONTOFFICE
    // ==============================================================

    /** Mes familles, mes invitations reçues, créer une famille */
    public function familyHome(): void
    {
        AuthController::requireAuth();
        $userId = $this->me();
        $this->expireInvitations();
        $errors = [];
        $old = ['nom' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $form = (string) ($_POST['form'] ?? '');
            if ($form === 'create') {
                $old['nom'] = trim((string) ($_POST['nom'] ?? ''));
                $result = $this->createFamily($userId, $_POST);
                if (is_int($result)) {
                    AuthController::setFlash('success', 'fam_msg_created');
                    $this->redirect('family_view', 'id=' . $result);
                }
                $errors = $result;
            } elseif ($form === 'respond') {
                $result = $this->respondInvitation($userId, $_POST);
                if (is_int($result)) {
                    AuthController::setFlash('success', 'fam_msg_joined');
                    $this->redirect('family_view', 'id=' . $result);
                }
                if ($result === []) {
                    AuthController::setFlash('success', 'fam_msg_refused');
                    $this->redirect('family');
                }
                $errors = $result;
            }
        }

        $this->renderFront('family', [
            'pageTitle'   => $this->t['fam_title'] . ' - LabTrack',
            'currentPage' => 'family',
            'families'    => $this->familiesOf($userId),
            'invitations' => $this->invitationsFor($userId),
            'myTag'       => (string) $this->scalarString('SELECT code FROM utilisateur WHERE id = :id', ['id' => $userId]),
            'errors'      => $errors,
            'old'         => $old,
            'flash'       => AuthController::pullFlash(),
        ]);
    }

    /** Tableau de bord d'une famille : aperçu, membres, calendrier, statistiques, messages */
    public function familyView(): void
    {
        AuthController::requireAuth();
        $userId = $this->me();
        $familyId = (int) ($_GET['id'] ?? 0);
        $this->expireInvitations();
        $this->repairManager($familyId);
        [$family, $myMember] = $this->requireMembership($familyId);

        $tabs = ['overview', 'members', 'calendar', 'stats', 'messages'];
        $tab = in_array($_GET['tab'] ?? '', $tabs, true) ? $_GET['tab'] : 'overview';
        $members = $this->membersOf($familyId);
        $visible = $this->visibleOwners($members, $userId);

        $data = [
            'pageTitle'   => $family->getNom() . ' - LabTrack',
            'currentPage' => 'family',
            'family'      => $family,
            'me'          => $myMember,
            'members'     => $members,
            'colors'      => $this->memberColors($members),
            'tab'         => $tab,
            'flash'       => AuthController::pullFlash(),
            'errors'      => $_SESSION['fam_errors'] ?? [],
            'old'         => $_SESSION['fam_old'] ?? [],
            'modules'     => $this->moduleStatus(),
        ];
        unset($_SESSION['fam_errors'], $_SESSION['fam_old']);

        if ($tab === 'overview') {
            $data['alerts']    = $this->alertsFor($familyId, $members, $myMember, $visible);
            $data['summary']   = $this->memberSummaries($members, $visible);
            $data['campaigns'] = $this->campaignsFor($members);
        } elseif ($tab === 'members') {
            $data['pending']  = $this->pendingInvitationsOf($familyId);
            $data['tagLock']  = $this->tagLockSeconds($userId);
        } elseif ($tab === 'calendar') {
            $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
            $data['month']    = $month;
            $data['events']   = $this->calendarEvents($visible, $month);
            $data['upcoming'] = $this->upcomingAppointments($visible, 14);
        } elseif ($tab === 'stats') {
            $data['stats'] = $this->medicationStats($members, $visible);
        } else {
            $data['messages'] = $this->messagesOf($familyId);
        }
        $this->renderFront('family_view', $data);
    }

    /** POST : inviter un compte avec son nom + son code privé (gestionnaire) */
    public function invite(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        $errors = $this->inviteByTag($familyId, $myMember, $_POST);
        if (empty($errors)) {
            AuthController::setFlash('success', 'fam_msg_invited');
        } else {
            $_SESSION['fam_errors'] = $errors;
            $_SESSION['fam_old'] = [
                'invite_nom' => trim((string) ($_POST['nom'] ?? '')),
                'invite_tag' => trim((string) ($_POST['tag'] ?? '')),
                'invite_relation' => (string) ($_POST['relation'] ?? ''),
            ];
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
    }

    /** POST : annuler une invitation en attente (gestionnaire) */
    public function cancelInvitation(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        if ($this->checkCsrf() && $myMember->getRole() === 'manager') {
            $stmt = $this->db->prepare("DELETE FROM famille_invitation WHERE id = :id AND famille_id = :f AND status = 'pending'");
            $stmt->execute(['id' => (int) ($_POST['invitation_id'] ?? 0), 'f' => $familyId]);
            AuthController::setFlash('success', 'fam_msg_invite_cancelled');
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
    }

    /** Ajouter (sans ?member=) ou modifier un profil à charge */
    public function memberForm(): void
    {
        AuthController::requireAuth();
        $familyId = (int) ($_GET['id'] ?? $_POST['family_id'] ?? 0);
        [$family, $myMember] = $this->requireMembership($familyId);
        $memberId = (int) ($_GET['member'] ?? 0);
        $member = $memberId > 0 ? $this->findMember($familyId, $memberId) : null;

        // Ajouter : gestionnaire. Modifier : gestionnaire ou aidant, et seulement un profil à charge.
        $allowed = $member === null
            ? $myMember->getRole() === 'manager'
            : $member->isDependent() && in_array($myMember->getRole(), ['manager', 'caregiver'], true);
        if (!$allowed) {
            $this->forbidden($familyId);
        }

        $errors = [];
        $old = $member
            ? ['nom' => $member->getNom(), 'relation' => $member->getRelation(), 'date_naissance' => (string) $member->getDateNaissance()]
            : ['nom' => '', 'relation' => 'fils', 'date_naissance' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = [
                'nom'            => trim((string) ($_POST['nom'] ?? '')),
                'relation'       => (string) ($_POST['relation'] ?? ''),
                'date_naissance' => trim((string) ($_POST['date_naissance'] ?? '')),
            ];
            $errors = $this->saveDependent($familyId, $member, $_POST);
            if (empty($errors)) {
                AuthController::setFlash('success', $member ? 'fam_msg_dependent_updated' : 'fam_msg_dependent_added');
                $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
            }
        }

        $this->renderFront('family_member', [
            'pageTitle'   => ($member ? $this->t['fam_edit_dependent'] : $this->t['fam_add_dependent']) . ' - LabTrack',
            'currentPage' => 'family',
            'family'      => $family,
            'member'      => $member,
            'old'         => $old,
            'errors'      => $errors,
        ]);
    }

    /** POST : retirer un membre ou supprimer un profil à charge (gestionnaire) */
    public function removeMember(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        $target = $this->findMember($familyId, (int) ($_POST['member_id'] ?? 0));
        if ($this->checkCsrf() && $myMember->getRole() === 'manager' && $target && $target->getId() !== $myMember->getId()) {
            $stmt = $this->db->prepare('DELETE FROM famille_membre WHERE id = :id AND famille_id = :f');
            $stmt->execute(['id' => $target->getId(), 'f' => $familyId]);
            AuthController::setFlash('success', 'fam_msg_member_removed');
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
    }

    /**
     * POST : changer le rôle d'un compte membre (gestionnaire).
     * Donner le rôle "gestionnaire" le transmet : l'ancien gestionnaire devient aidant.
     */
    public function changeRole(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        $target = $this->findMember($familyId, (int) ($_POST['member_id'] ?? 0));
        $role = (string) ($_POST['role'] ?? '');
        if ($this->checkCsrf() && $myMember->getRole() === 'manager' && $target && !$target->isDependent()
            && $target->getId() !== $myMember->getId() && in_array($role, self::ROLES, true)) {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('UPDATE famille_membre SET role = :r WHERE id = :id AND famille_id = :f');
            $stmt->execute(['r' => $role, 'id' => $target->getId(), 'f' => $familyId]);
            if ($role === 'manager') {
                $stmt->execute(['r' => 'caregiver', 'id' => $myMember->getId(), 'f' => $familyId]);
            }
            $this->db->commit();
            AuthController::setFlash('success', $role === 'manager' ? 'fam_msg_manager_transferred' : 'fam_msg_role_updated');
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
    }

    /** POST : mes choix de partage dans cette famille (consentement, modifiable à tout moment) */
    public function updateShares(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        if ($this->checkCsrf()) {
            $shares = $this->sharesFromPost($_POST);
            $stmt = $this->db->prepare(
                'UPDATE famille_membre SET share_analyses = :a, share_dossier = :d, share_rappels = :r, share_rdv = :v
                 WHERE id = :id AND user_id = :u'
            );
            $stmt->execute($shares + ['id' => $myMember->getId(), 'u' => $this->me()]);
            AuthController::setFlash('success', 'fam_msg_shares_updated');
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
    }

    /** POST : quitter la famille (tout membre sauf le gestionnaire, qui doit d'abord transmettre son rôle) */
    public function leave(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        if (!$this->checkCsrf()) {
            $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
        }
        if ($myMember->getRole() === 'manager') {
            AuthController::setFlash('error', 'fam_err_manager_leave');
            $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
        }
        $stmt = $this->db->prepare('DELETE FROM famille_membre WHERE id = :id AND user_id = :u');
        $stmt->execute(['id' => $myMember->getId(), 'u' => $this->me()]);
        AuthController::setFlash('success', 'fam_msg_left');
        $this->redirect('family');
    }

    /** POST : renommer la famille (gestionnaire) */
    public function rename(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        $nom = trim((string) ($_POST['nom'] ?? ''));
        if ($this->checkCsrf() && $myMember->getRole() === 'manager') {
            if (preg_match(self::FAMILLE_REGEX, $nom)) {
                $stmt = $this->db->prepare('UPDATE famille SET nom = :n WHERE id = :id');
                $stmt->execute(['n' => $nom, 'id' => $familyId]);
                AuthController::setFlash('success', 'fam_msg_renamed');
            } else {
                $_SESSION['fam_errors'] = [$this->t['fam_err_name']];
            }
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
    }

    /** POST : supprimer la famille, ses profils à charge, invitations et messages (gestionnaire) */
    public function deleteFamily(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        if ($this->checkCsrf() && $myMember->getRole() === 'manager') {
            $this->db->prepare('DELETE FROM famille WHERE id = :id')->execute(['id' => $familyId]);
            AuthController::setFlash('success', 'fam_msg_deleted');
            $this->redirect('family');
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=members');
    }

    /** POST : publier un message dans la famille */
    public function postMessage(): void
    {
        $familyId = $this->postFamilyId();
        $this->requireMembership($familyId);
        $text = trim((string) ($_POST['contenu'] ?? ''));
        if ($this->checkCsrf()) {
            if ($text === '' || mb_strlen($text) > self::MESSAGE_MAX) {
                $_SESSION['fam_errors'] = [sprintf($this->t['fam_err_message'], self::MESSAGE_MAX)];
                $_SESSION['fam_old'] = ['contenu' => mb_substr($text, 0, self::MESSAGE_MAX + 100)];
            } else {
                $stmt = $this->db->prepare('INSERT INTO famille_message (famille_id, user_id, contenu) VALUES (:f, :u, :c)');
                $stmt->execute(['f' => $familyId, 'u' => $this->me(), 'c' => $text]);
            }
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=messages');
    }

    /** POST : supprimer un message (son auteur, ou le gestionnaire) */
    public function deleteMessage(): void
    {
        $familyId = $this->postFamilyId();
        [, $myMember] = $this->requireMembership($familyId);
        if ($this->checkCsrf()) {
            $sql = 'DELETE FROM famille_message WHERE id = :id AND famille_id = :f';
            $params = ['id' => (int) ($_POST['message_id'] ?? 0), 'f' => $familyId];
            if ($myMember->getRole() !== 'manager') {
                $sql .= ' AND user_id = :u';
                $params['u'] = $this->me();
            }
            $this->db->prepare($sql)->execute($params);
        }
        $this->redirect('family_view', 'id=' . $familyId . '&tab=messages');
    }

    /**
     * Carte d'urgence d'un membre : groupe sanguin, allergies, maladies, traitements,
     * contact d'urgence + QR code qui ouvre la carte (à montrer à l'hôpital, ou à imprimer).
     * Modifier : le compte lui-même, ou gestionnaire / aidant pour un profil à charge.
     */
    public function emergencyCard(): void
    {
        AuthController::requireAuth();
        $familyId = (int) ($_GET['id'] ?? $_POST['family_id'] ?? 0);
        [$family, $myMember] = $this->requireMembership($familyId);
        $member = $this->findMember($familyId, (int) ($_GET['member'] ?? $_POST['member_id'] ?? 0));
        if (!$member) {
            $this->forbidden($familyId);
        }
        $isSelf = $member->getUserId() === $this->me();
        $canEdit = $isSelf || ($member->isDependent() && in_array($myMember->getRole(), ['manager', 'caregiver'], true));
        // Voir la carte d'un autre compte : seulement s'il partage son dossier médical
        if (!$canEdit && !$member->shares('dossier')) {
            $this->forbidden($familyId);
        }

        $errors = [];
        $card = $this->findCard($member);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
            $action = (string) ($_POST['form'] ?? 'save');
            if (!$this->checkCsrf()) {
                $errors[] = $this->t['err_csrf'];
            } elseif ($action === 'save') {
                $errors = $this->saveCard($member, $card, $_POST);
                if (empty($errors)) {
                    AuthController::setFlash('success', 'fam_msg_card_saved');
                }
            } elseif ($action === 'qr_new') {
                $this->setCardToken($member, bin2hex(random_bytes(16)));
                AuthController::setFlash('success', 'fam_msg_qr_new');
            } elseif ($action === 'qr_off') {
                $this->setCardToken($member, null);
                AuthController::setFlash('success', 'fam_msg_qr_off');
            }
            if (empty($errors)) {
                $this->redirect('family_card', 'id=' . $familyId . '&member=' . $member->getId());
            }
            $card = array_merge($card ?? [], array_intersect_key($_POST, array_flip(['groupe_sanguin', 'allergies', 'maladies', 'traitements', 'contact_urgence'])));
        }

        $card = $card ?? $this->findCard($member);
        $publicUrl = !empty($card['token']) ? $this->publicCardUrl($card['token']) : null;
        $this->renderFront('family_card', [
            'pageTitle'   => $this->t['fam_card_title'] . ' - ' . $member->getNom(),
            'currentPage' => 'family',
            'family'      => $family,
            'member'      => $member,
            'card'        => $card,
            'canEdit'     => $canEdit,
            'publicUrl'   => $publicUrl,
            'qrSvg'       => $publicUrl ? QrCode::svg($publicUrl, 200, $this->t['fam_qr_label']) : null,
            'errors'      => $errors,
            'flash'       => AuthController::pullFlash(),
        ]);
    }

    /**
     * Page publique ouverte par le QR code : seulement les informations d'urgence.
     * Le jeton (128 bits aléatoires) est impossible à deviner ; il peut être changé
     * ou désactivé à tout moment par le propriétaire de la carte.
     */
    public function publicCard(): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        $token = (string) ($_GET['t'] ?? '');
        $card = null;
        if (preg_match('/^[a-f0-9]{32}$/', $token)) {
            $stmt = $this->db->prepare(
                'SELECT c.*, COALESCE(u.nom, m.nom) AS nom, m.date_naissance
                 FROM fiche_urgence c
                 LEFT JOIN utilisateur u ON u.id = c.user_id
                 LEFT JOIN famille_membre m ON m.id = c.membre_id
                 WHERE c.token = :t LIMIT 1'
            );
            $stmt->execute(['t' => $token]);
            $card = $stmt->fetch() ?: null;
        }
        if (!$card) {
            http_response_code(404);
        }
        $this->renderFront('family_public_card', [
            'pageTitle'   => $this->t['fam_card_title'] . ' - LabTrack',
            'currentPage' => 'urgence',
            'card'        => $card,
            'age'         => $card && $card['date_naissance'] ? (new DateTime($card['date_naissance']))->diff(new DateTime('today'))->y : null,
        ]);
    }

    // ==============================================================
    // BACKOFFICE (rôle admin) : familles et membres, JAMAIS les données de santé
    // ==============================================================

    public function adminFamilies(): void
    {
        AuthController::requireRole('admin');
        $query = trim((string) ($_GET['q'] ?? ''));
        $sql = "SELECT f.id, f.nom, f.created_at,
                       (SELECT COUNT(*) FROM famille_membre m WHERE m.famille_id = f.id) AS nb_membres,
                       (SELECT COUNT(*) FROM famille_membre m WHERE m.famille_id = f.id AND m.user_id IS NULL) AS nb_charges,
                       (SELECT u.nom FROM famille_membre m JOIN utilisateur u ON u.id = m.user_id
                         WHERE m.famille_id = f.id AND m.role = 'manager' LIMIT 1) AS gestionnaire
                FROM famille f";
        $params = [];
        if ($query !== '') {
            $sql .= ' WHERE f.nom LIKE :q';
            $params['q'] = '%' . $query . '%';
        }
        $sql .= ' ORDER BY f.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $families = $stmt->fetchAll();

        $count = fn(string $sql): int => (int) $this->db->query($sql)->fetchColumn();
        $totalFamilies = $count('SELECT COUNT(*) FROM famille');
        $totalMembers = $count('SELECT COUNT(*) FROM famille_membre');

        $this->renderAdmin('families', [
            'title'       => $this->t['fam_admin_title'] . ' · LabTrack Admin',
            'heading'     => $this->t['fam_admin_title'],
            'currentPage' => 'admin_families',
            'families'    => $families,
            'query'       => $query,
            'stats'       => [
                'families'   => $totalFamilies,
                'average'    => $totalFamilies > 0 ? round($totalMembers / $totalFamilies, 1) : 0,
                'dependents' => $count('SELECT COUNT(*) FROM famille_membre WHERE user_id IS NULL'),
                'pending'    => $count("SELECT COUNT(*) FROM famille_invitation WHERE status = 'pending' AND expires_at > NOW()"),
            ],
        ], 'families_scripts');
    }

    public function adminFamily(): void
    {
        AuthController::requireRole('admin');
        $familyId = (int) ($_GET['id'] ?? 0);
        $family = $this->findFamily($familyId);
        if (!$family) {
            AuthController::setFlash('error', 'fam_err_not_found');
            $this->redirect('admin_families');
        }
        $this->renderAdmin('family_detail', [
            'title'       => $family->getNom() . ' · LabTrack Admin',
            'heading'     => $family->getNom(),
            'currentPage' => 'admin_families',
            'family'      => $family,
            'members'     => $this->membersOf($familyId),
            'pending'     => $this->pendingInvitationsOf($familyId),
            'messageCount'=> (int) $this->scalar('SELECT COUNT(*) FROM famille_message WHERE famille_id = :f', ['f' => $familyId]),
        ], 'families_scripts');
    }

    /** POST : supprimer une famille (abus, signalement) */
    public function adminDeleteFamily(): void
    {
        AuthController::requireRole('admin');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->checkCsrf()) {
            $this->db->prepare('DELETE FROM famille WHERE id = :id')->execute(['id' => (int) ($_POST['family_id'] ?? 0)]);
            AuthController::setFlash('success', 'fam_msg_deleted');
        }
        $this->redirect('admin_families');
    }

    /** POST : retirer un membre d'une famille (le gestionnaire ne peut pas être retiré ici) */
    public function adminRemoveMember(): void
    {
        AuthController::requireRole('admin');
        $familyId = (int) ($_POST['family_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->checkCsrf()) {
            $stmt = $this->db->prepare("DELETE FROM famille_membre WHERE id = :id AND famille_id = :f AND role <> 'manager'");
            $stmt->execute(['id' => (int) ($_POST['member_id'] ?? 0), 'f' => $familyId]);
            AuthController::setFlash('success', 'fam_msg_member_removed');
        }
        $this->redirect('admin_family', 'id=' . $familyId);
    }

    // ==============================================================
    // LOGIQUE MÉTIER
    // ==============================================================

    /** Crée une famille ; son créateur en devient le gestionnaire. Retourne l'id ou les erreurs. */
    private function createFamily(int $userId, array $post): int|array
    {
        if (!$this->checkCsrf()) {
            return [$this->t['err_csrf']];
        }
        $nom = trim((string) ($post['nom'] ?? ''));
        if (!preg_match(self::FAMILLE_REGEX, $nom)) {
            return [$this->t['fam_err_name']];
        }
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('INSERT INTO famille (nom, created_by) VALUES (:n, :u)');
        $stmt->execute(['n' => $nom, 'u' => $userId]);
        $familyId = (int) $this->db->lastInsertId();
        // Le gestionnaire voit ses propres données ; pour la famille, il partage tout par défaut (modifiable)
        $stmt = $this->db->prepare(
            "INSERT INTO famille_membre (famille_id, user_id, relation, role, share_analyses, share_dossier, share_rappels, share_rdv)
             VALUES (:f, :u, 'autre', 'manager', 1, 1, 1, 1)"
        );
        $stmt->execute(['f' => $familyId, 'u' => $userId]);
        $this->db->commit();
        return $familyId;
    }

    /**
     * Invitation par NOM + CODE PRIVÉ uniquement.
     * Même message pour "code inconnu" et "nom différent" : impossible de découvrir les codes.
     */
    private function inviteByTag(int $familyId, MembreModel $myMember, array $post): array
    {
        if (!$this->checkCsrf()) {
            return [$this->t['err_csrf']];
        }
        if ($myMember->getRole() !== 'manager') {
            return [$this->t['fam_err_forbidden']];
        }
        $me = $this->me();
        $lock = $this->tagLockSeconds($me);
        if ($lock > 0) {
            return [sprintf($this->t['fam_err_tag_locked'], (int) ceil($lock / 60))];
        }

        $nom = trim((string) ($post['nom'] ?? ''));
        $tag = ltrim(trim((string) ($post['tag'] ?? '')), '#');
        $relation = (string) ($post['relation'] ?? '');
        $linkMemberId = (int) ($post['link_member'] ?? 0);
        $errors = [];
        if (!preg_match(self::NOM_REGEX, $nom)) {
            $errors[] = $this->t['err_nom'];
        }
        if (!preg_match('/^\d{4}$/', $tag)) {
            $errors[] = $this->t['fam_err_tag_format'];
        }
        if (!in_array($relation, self::RELATIONS, true)) {
            $errors[] = $this->t['fam_err_relation'];
        }
        if ($errors) {
            return $errors;
        }

        // Le nom est comparé sans tenir compte des majuscules (collation utf8mb4_unicode_ci)
        $stmt = $this->db->prepare('SELECT * FROM utilisateur WHERE code = :c AND nom = :n LIMIT 1');
        $stmt->execute(['c' => $tag, 'n' => $nom]);
        $row = $stmt->fetch();
        if (!$row) {
            $this->db->prepare('INSERT INTO famille_tentative (user_id) VALUES (:u)')->execute(['u' => $me]);
            return [$this->t['fam_err_tag_not_found']];
        }
        $invitee = UserModel::fromArray($row);

        if ($invitee->getId() === $me) {
            return [$this->t['fam_err_self']];
        }
        if ($this->scalar('SELECT COUNT(*) FROM famille_membre WHERE famille_id = :f AND user_id = :u', ['f' => $familyId, 'u' => $invitee->getId()]) > 0) {
            return [$this->t['fam_err_already_member']];
        }
        if ($this->scalar("SELECT COUNT(*) FROM famille_invitation WHERE famille_id = :f AND invited_user_id = :u AND status = 'pending'", ['f' => $familyId, 'u' => $invitee->getId()]) > 0) {
            return [$this->t['fam_err_already_invited']];
        }
        if ($linkMemberId === 0 && $this->scalar('SELECT COUNT(*) FROM famille_membre WHERE famille_id = :f', ['f' => $familyId]) >= self::MAX_MEMBERS) {
            return [sprintf($this->t['fam_err_full'], self::MAX_MEMBERS)];
        }
        // Relier un profil à charge à son nouveau compte (l'enfant qui a grandi)
        if ($linkMemberId > 0) {
            $dependent = $this->findMember($familyId, $linkMemberId);
            if (!$dependent || !$dependent->isDependent()) {
                return [$this->t['fam_err_forbidden']];
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO famille_invitation (famille_id, invited_by, invited_user_id, membre_id, relation, expires_at)
             VALUES (:f, :by, :u, :m, :r, NOW() + INTERVAL ' . self::INVITE_HOURS . ' HOUR)'
        );
        $stmt->execute([
            'f'  => $familyId,
            'by' => $me,
            'u'  => $invitee->getId(),
            'm'  => $linkMemberId > 0 ? $linkMemberId : null,
            'r'  => $relation,
        ]);
        $this->sendInvitationEmail($invitee, $familyId);
        return [];
    }

    /** Notification par email dans la langue de l'invité (un échec d'envoi n'annule pas l'invitation) */
    private function sendInvitationEmail(UserModel $invitee, int $familyId): void
    {
        $family = $this->findFamily($familyId);
        $inviter = $this->db->prepare('SELECT nom FROM utilisateur WHERE id = :id');
        $inviter->execute(['id' => $this->me()]);
        $t = AuthController::translationsFor($invitee->getLangue());
        $body = sprintf($t['fam_mail_body'], $invitee->getNom(), (string) $inviter->fetchColumn(), $family ? $family->getNom() : '', self::INVITE_HOURS);
        (new Mailer())->send($invitee->getEmail(), $t['fam_mail_subject'], $body);
    }

    /**
     * Accepter (avec ses choix de partage) ou refuser une invitation.
     * Retourne l'id de la famille rejointe, [] si refusée, ou les erreurs.
     */
    private function respondInvitation(int $userId, array $post): int|array
    {
        if (!$this->checkCsrf()) {
            return [$this->t['err_csrf']];
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM famille_invitation
             WHERE id = :id AND invited_user_id = :u AND status = 'pending' AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute(['id' => (int) ($post['invitation_id'] ?? 0), 'u' => $userId]);
        $inv = $stmt->fetch();
        if (!$inv) {
            return [$this->t['fam_err_invitation']];
        }
        $answer = (string) ($post['answer'] ?? '');
        $update = $this->db->prepare('UPDATE famille_invitation SET status = :s, responded_at = NOW() WHERE id = :id');
        if ($answer !== 'accept') {
            $update->execute(['s' => 'refused', 'id' => (int) $inv['id']]);
            return [];
        }

        $familyId = (int) $inv['famille_id'];
        $shares = $this->sharesFromPost($post);
        $this->db->beginTransaction();
        $dependent = $inv['membre_id'] !== null ? $this->findMember($familyId, (int) $inv['membre_id']) : null;
        if ($dependent && $dependent->isDependent()) {
            // Le profil à charge devient ce compte : il garde son historique, l'invité décide des partages
            $stmt = $this->db->prepare(
                "UPDATE famille_membre SET user_id = :u, role = 'member', share_analyses = :a, share_dossier = :d,
                        share_rappels = :r, share_rdv = :v WHERE id = :id"
            );
            $stmt->execute($shares + ['u' => $userId, 'id' => $dependent->getId()]);
            // Sa carte d'urgence passe sur son compte s'il n'en a pas déjà une
            if ($this->scalar('SELECT COUNT(*) FROM fiche_urgence WHERE user_id = :u', ['u' => $userId]) === 0) {
                $this->db->prepare('UPDATE fiche_urgence SET user_id = :u, membre_id = NULL WHERE membre_id = :m')
                    ->execute(['u' => $userId, 'm' => $dependent->getId()]);
            }
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO famille_membre (famille_id, user_id, relation, role, share_analyses, share_dossier, share_rappels, share_rdv)
                 VALUES (:f, :u, :rel, 'member', :a, :d, :r, :v)"
            );
            $stmt->execute($shares + ['f' => $familyId, 'u' => $userId, 'rel' => $inv['relation']]);
        }
        $update->execute(['s' => 'accepted', 'id' => (int) $inv['id']]);
        $this->db->commit();
        return $familyId;
    }

    /** Ajoute ou modifie un profil à charge */
    private function saveDependent(int $familyId, ?MembreModel $member, array $post): array
    {
        if (!$this->checkCsrf()) {
            return [$this->t['err_csrf']];
        }
        $nom = trim((string) ($post['nom'] ?? ''));
        $relation = (string) ($post['relation'] ?? '');
        $birth = trim((string) ($post['date_naissance'] ?? ''));
        $errors = [];
        if (!preg_match(self::NOM_REGEX, $nom)) {
            $errors[] = $this->t['err_nom'];
        }
        if (!in_array($relation, self::RELATIONS, true)) {
            $errors[] = $this->t['fam_err_relation'];
        }
        $date = DateTime::createFromFormat('!Y-m-d', $birth);
        if (!$date || $date->format('Y-m-d') !== $birth || $date > new DateTime('today') || $date < new DateTime('-120 years')) {
            $errors[] = $this->t['fam_err_birth'];
        }
        if (!$member && $this->scalar('SELECT COUNT(*) FROM famille_membre WHERE famille_id = :f', ['f' => $familyId]) >= self::MAX_MEMBERS) {
            $errors[] = sprintf($this->t['fam_err_full'], self::MAX_MEMBERS);
        }
        if ($errors) {
            return $errors;
        }
        if ($member) {
            $stmt = $this->db->prepare(
                'UPDATE famille_membre SET nom = :n, relation = :r, date_naissance = :d
                 WHERE id = :id AND famille_id = :f AND user_id IS NULL'
            );
            $stmt->execute(['n' => $nom, 'r' => $relation, 'd' => $birth, 'id' => $member->getId(), 'f' => $familyId]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO famille_membre (famille_id, user_id, nom, relation, date_naissance, role)
                 VALUES (:f, NULL, :n, :r, :d, 'member')"
            );
            $stmt->execute(['f' => $familyId, 'n' => $nom, 'r' => $relation, 'd' => $birth]);
        }
        return [];
    }

    private function saveCard(MembreModel $member, ?array $card, array $post): array
    {
        $values = [
            'g'  => (string) ($post['groupe_sanguin'] ?? ''),
            'al' => trim((string) ($post['allergies'] ?? '')),
            'ma' => trim((string) ($post['maladies'] ?? '')),
            'tr' => trim((string) ($post['traitements'] ?? '')),
            'co' => trim((string) ($post['contact_urgence'] ?? '')),
        ];
        $errors = [];
        if ($values['g'] !== '' && !in_array($values['g'], self::BLOOD_GROUPS, true)) {
            $errors[] = $this->t['fam_err_blood'];
        }
        foreach (['al', 'ma', 'tr'] as $k) {
            if (mb_strlen($values[$k]) > self::TEXT_MAX) {
                $errors[] = sprintf($this->t['fam_err_text'], self::TEXT_MAX);
                break;
            }
        }
        if (mb_strlen($values['co']) > 150) {
            $errors[] = sprintf($this->t['fam_err_text'], 150);
        }
        if ($errors) {
            return $errors;
        }
        $values['g'] = $values['g'] !== '' ? $values['g'] : null;
        if ($card) {
            $stmt = $this->db->prepare(
                'UPDATE fiche_urgence SET groupe_sanguin = :g, allergies = :al, maladies = :ma, traitements = :tr,
                        contact_urgence = :co WHERE id = :id'
            );
            $stmt->execute($values + ['id' => (int) $card['id']]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO fiche_urgence (user_id, membre_id, groupe_sanguin, allergies, maladies, traitements, contact_urgence)
                 VALUES (:u, :m, :g, :al, :ma, :tr, :co)'
            );
            $stmt->execute($values + [
                'u' => $member->isDependent() ? null : $member->getUserId(),
                'm' => $member->isDependent() ? $member->getId() : null,
            ]);
        }
        return [];
    }

    private function setCardToken(MembreModel $member, ?string $token): void
    {
        if (!$this->findCard($member)) {
            $this->saveCard($member, null, []);
        }
        [$col, $val] = $member->isDependent() ? ['membre_id', $member->getId()] : ['user_id', $member->getUserId()];
        $this->db->prepare("UPDATE fiche_urgence SET token = :t WHERE $col = :v")->execute(['t' => $token, 'v' => $val]);
    }

    private function findCard(MembreModel $member): ?array
    {
        [$col, $val] = $member->isDependent() ? ['membre_id', $member->getId()] : ['user_id', $member->getUserId()];
        $stmt = $this->db->prepare("SELECT * FROM fiche_urgence WHERE $col = :v LIMIT 1");
        $stmt->execute(['v' => $val]);
        return $stmt->fetch() ?: null;
    }

    /** Adresse complète de la carte publique (encodée dans le QR code) */
    private function publicCardUrl(string $token): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
        return ($https ? 'https' : 'http') . '://' . $host . $dir . '/index.php?page=urgence&t=' . $token;
    }

    // ==============================================================
    // DONNÉES DES AUTRES MODULES (lues si les tables existent + consentement)
    // ==============================================================

    /** Une table d'un autre module existe-t-elle déjà ? */
    private function hasTable(string $name): bool
    {
        if (!array_key_exists($name, $this->tableCache)) {
            $this->tableCache[$name] = (int) $this->scalar(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t',
                ['t' => $name]
            ) > 0;
        }
        return $this->tableCache[$name];
    }

    /** Modules branchés (pour expliquer à l'utilisateur ce qui n'est pas encore disponible) */
    private function moduleStatus(): array
    {
        return [
            'rdv'       => $this->hasTable('rendez_vous'),
            'rappels'   => $this->hasTable('rappel_medicament') && $this->hasTable('prise_medicament'),
            'analyses'  => $this->hasTable('bilan_sanguin'),
            'campagnes' => $this->hasTable('campagne_sante'),
        ];
    }

    /**
     * Pour chaque type de données : les propriétaires visibles par le compte connecté.
     * Un compte voit toujours ses propres données ; celles des autres comptes seulement
     * s'ils les partagent ; les profils à charge sont visibles par toute la famille.
     * @return array<string, array{users: array<int,int>, membres: array<int,int>}>  id => id du membre
     */
    private function visibleOwners(array $members, int $me): array
    {
        $out = [];
        foreach (self::SHARES as $type) {
            $out[$type] = ['users' => [], 'membres' => []];
            foreach ($members as $m) { /** @var MembreModel $m */
                if ($m->isDependent()) {
                    $out[$type]['membres'][$m->getId()] = $m->getId();
                } elseif ($m->getUserId() === $me || $m->shares($type)) {
                    $out[$type]['users'][$m->getUserId()] = $m->getId();
                }
            }
        }
        return $out;
    }

    /** Condition SQL "appartient à un propriétaire visible" (ids entiers : aucune donnée utilisateur dans le SQL) */
    private function ownerWhere(array $owners, string $alias = ''): ?string
    {
        $p = $alias !== '' ? $alias . '.' : '';
        $parts = [];
        if ($owners['users']) {
            $parts[] = $p . 'user_id IN (' . implode(',', array_map('intval', array_keys($owners['users']))) . ')';
        }
        if ($owners['membres']) {
            $parts[] = $p . 'membre_id IN (' . implode(',', array_map('intval', array_keys($owners['membres']))) . ')';
        }
        return $parts ? '(' . implode(' OR ', $parts) . ')' : null;
    }

    /** Retrouve le membre de la famille propriétaire d'une ligne (user_id ou membre_id) */
    private function ownerMemberId(array $owners, array $row): ?int
    {
        if (!empty($row['membre_id']) && isset($owners['membres'][(int) $row['membre_id']])) {
            return $owners['membres'][(int) $row['membre_id']];
        }
        if (!empty($row['user_id']) && isset($owners['users'][(int) $row['user_id']])) {
            return $owners['users'][(int) $row['user_id']];
        }
        return null;
    }

    /** Calendrier du mois : rendez-vous + heures de médicaments, par jour */
    private function calendarEvents(array $visible, string $month): array
    {
        $start = new DateTime($month . '-01');
        $end = (clone $start)->modify('last day of this month');
        $events = [];

        if ($this->hasTable('rendez_vous') && ($where = $this->ownerWhere($visible['rdv']))) {
            $stmt = $this->db->prepare(
                "SELECT id, user_id, membre_id, titre, date_rdv, lieu FROM rendez_vous
                 WHERE $where AND date_rdv BETWEEN :a AND :b ORDER BY date_rdv"
            );
            $stmt->execute(['a' => $start->format('Y-m-d 00:00:00'), 'b' => $end->format('Y-m-d 23:59:59')]);
            foreach ($stmt->fetchAll() as $row) {
                $day = substr($row['date_rdv'], 0, 10);
                $events[$day][] = [
                    'type'   => 'rdv',
                    'member' => $this->ownerMemberId($visible['rdv'], $row),
                    'time'   => substr($row['date_rdv'], 11, 5),
                    'title'  => $row['titre'],
                    'place'  => $row['lieu'] ?? '',
                ];
            }
        }

        if ($this->hasTable('rappel_medicament') && ($where = $this->ownerWhere($visible['rappels']))) {
            $stmt = $this->db->prepare(
                "SELECT id, user_id, membre_id, medicament, dose, heure, date_debut, date_fin FROM rappel_medicament
                 WHERE $where AND date_debut <= :b AND (date_fin IS NULL OR date_fin >= :a) ORDER BY heure"
            );
            $stmt->execute(['a' => $start->format('Y-m-d'), 'b' => $end->format('Y-m-d')]);
            foreach ($stmt->fetchAll() as $row) {
                $from = max($start->format('Y-m-d'), $row['date_debut']);
                $to = min($end->format('Y-m-d'), $row['date_fin'] ?? $end->format('Y-m-d'));
                for ($d = new DateTime($from); $d->format('Y-m-d') <= $to; $d->modify('+1 day')) {
                    $events[$d->format('Y-m-d')][] = [
                        'type'   => 'med',
                        'member' => $this->ownerMemberId($visible['rappels'], $row),
                        'time'   => substr($row['heure'], 0, 5),
                        'title'  => trim($row['medicament'] . ' ' . ($row['dose'] ?? '')),
                        'place'  => '',
                    ];
                }
            }
        }

        foreach ($events as &$list) {
            usort($list, fn($a, $b) => strcmp($a['time'], $b['time']));
        }
        return $events;
    }

    private function upcomingAppointments(array $visible, int $days): array
    {
        if (!$this->hasTable('rendez_vous') || !($where = $this->ownerWhere($visible['rdv']))) {
            return [];
        }
        $stmt = $this->db->query(
            "SELECT user_id, membre_id, titre, date_rdv, lieu, medecin FROM rendez_vous
             WHERE $where AND date_rdv BETWEEN NOW() AND NOW() + INTERVAL " . (int) $days . ' DAY ORDER BY date_rdv LIMIT 20'
        );
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['member'] = $this->ownerMemberId($visible['rdv'], $row);
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Prises de médicaments des 7 derniers jours, par membre : prévues, prises, oubliées.
     * Une prise prévue dont l'heure est passée sans être cochée "pris" compte comme oubliée.
     */
    private function medicationStats(array $members, array $visible): array
    {
        $stats = [];
        if (!$this->moduleStatus()['rappels'] || !($where = $this->ownerWhere($visible['rappels'], 'r'))) {
            return $stats;
        }
        $from = new DateTime('-6 days');
        $from->setTime(0, 0);
        $today = new DateTime('today');
        $now = new DateTime();

        $stmt = $this->db->prepare(
            "SELECT r.id, r.user_id, r.membre_id, r.heure, r.date_debut, r.date_fin FROM rappel_medicament r
             WHERE $where AND r.date_debut <= :b AND (r.date_fin IS NULL OR r.date_fin >= :a)"
        );
        $stmt->execute(['a' => $from->format('Y-m-d'), 'b' => $today->format('Y-m-d')]);
        $reminders = $stmt->fetchAll();
        if (!$reminders) {
            return $stats;
        }
        $ids = implode(',', array_map(fn($r) => (int) $r['id'], $reminders));
        $taken = [];
        $stmt = $this->db->prepare(
            "SELECT rappel_id, date_prise FROM prise_medicament
             WHERE rappel_id IN ($ids) AND statut = 'pris' AND date_prise >= :a"
        );
        $stmt->execute(['a' => $from->format('Y-m-d')]);
        foreach ($stmt->fetchAll() as $p) {
            $taken[$p['rappel_id'] . '|' . $p['date_prise']] = true;
        }

        foreach ($reminders as $r) {
            $memberId = $this->ownerMemberId($visible['rappels'], $r);
            if ($memberId === null) {
                continue;
            }
            $stats[$memberId] ??= ['planned' => 0, 'taken' => 0, 'missed' => 0];
            for ($d = clone $from; $d <= $today; $d->modify('+1 day')) {
                $day = $d->format('Y-m-d');
                if ($day < $r['date_debut'] || ($r['date_fin'] !== null && $day > $r['date_fin'])) {
                    continue;
                }
                $due = new DateTime($day . ' ' . $r['heure']);
                if ($due > $now) {
                    continue; // pas encore l'heure
                }
                $stats[$memberId]['planned']++;
                isset($taken[$r['id'] . '|' . $day]) ? $stats[$memberId]['taken']++ : $stats[$memberId]['missed']++;
            }
        }
        return $stats;
    }

    /** Alertes douces de la famille (jamais alarmistes) */
    private function alertsFor(int $familyId, array $members, MembreModel $me, array $visible): array
    {
        $alerts = [];
        $names = [];
        foreach ($members as $m) {
            $names[$m->getId()] = $m->getNom();
        }
        $caring = in_array($me->getRole(), ['manager', 'caregiver'], true);

        // Médicament de la journée pas encore coché "pris" (aidants et gestionnaire)
        if ($caring && $this->moduleStatus()['rappels'] && ($where = $this->ownerWhere($visible['rappels'], 'r'))) {
            $stmt = $this->db->query(
                "SELECT r.user_id, r.membre_id, r.medicament, r.heure FROM rappel_medicament r
                 WHERE $where AND r.date_debut <= CURDATE() AND (r.date_fin IS NULL OR r.date_fin >= CURDATE())
                   AND r.heure <= CURTIME()
                   AND NOT EXISTS (SELECT 1 FROM prise_medicament p WHERE p.rappel_id = r.id
                                   AND p.date_prise = CURDATE() AND p.statut = 'pris')
                 ORDER BY r.heure"
            );
            foreach ($stmt->fetchAll() as $row) {
                $mid = $this->ownerMemberId($visible['rappels'], $row);
                $alerts[] = ['level' => 'warning', 'icon' => 'icofont-pills',
                    'text' => sprintf($this->t['fam_alert_med'], $names[$mid] ?? '', $row['medicament'], substr($row['heure'], 0, 5))];
            }
        }
        // Rendez-vous dans les 48 prochaines heures (tout le monde)
        foreach ($this->upcomingAppointments($visible, 2) as $row) {
            $alerts[] = ['level' => 'info', 'icon' => 'icofont-calendar',
                'text' => sprintf($this->t['fam_alert_rdv'], $names[$row['member']] ?? '', $row['titre'], substr($row['date_rdv'], 0, 16))];
        }
        // Résultat d'analyse hors norme ces 7 derniers jours (aidants et gestionnaire)
        if ($caring && $this->hasTable('bilan_sanguin') && ($where = $this->ownerWhere($visible['analyses']))) {
            $stmt = $this->db->query(
                "SELECT user_id, membre_id, titre, date_bilan FROM bilan_sanguin
                 WHERE $where AND hors_norme = 1 AND date_bilan >= CURDATE() - INTERVAL 7 DAY ORDER BY date_bilan DESC"
            );
            foreach ($stmt->fetchAll() as $row) {
                $mid = $this->ownerMemberId($visible['analyses'], $row);
                $alerts[] = ['level' => 'info', 'icon' => 'icofont-blood',
                    'text' => sprintf($this->t['fam_alert_test'], $names[$mid] ?? '', $row['titre'])];
            }
        }
        // Invitations en attente (gestionnaire)
        if ($me->getRole() === 'manager') {
            $n = count($this->pendingInvitationsOf($familyId));
            if ($n > 0) {
                $alerts[] = ['level' => 'neutral', 'icon' => 'icofont-paper-plane', 'text' => sprintf($this->t['fam_alert_pending'], $n)];
            }
        }
        return $alerts;
    }

    /** Résumé de chaque membre pour les cartes du tableau de bord */
    private function memberSummaries(array $members, array $visible): array
    {
        $out = [];
        foreach ($members as $m) {
            $out[$m->getId()] = ['next_rdv' => null, 'doses_today' => null, 'doses_taken' => null];
        }
        if ($this->hasTable('rendez_vous') && ($where = $this->ownerWhere($visible['rdv']))) {
            $rows = $this->db->query("SELECT user_id, membre_id, titre, date_rdv FROM rendez_vous
                WHERE $where AND date_rdv >= NOW() ORDER BY date_rdv LIMIT 200")->fetchAll();
            foreach ($rows as $row) {
                $mid = $this->ownerMemberId($visible['rdv'], $row);
                if ($mid !== null && $out[$mid]['next_rdv'] === null) {
                    $out[$mid]['next_rdv'] = $row;
                }
            }
        }
        if ($this->moduleStatus()['rappels'] && ($where = $this->ownerWhere($visible['rappels'], 'r'))) {
            $rows = $this->db->query(
                "SELECT r.user_id, r.membre_id, COUNT(*) AS total,
                        SUM(EXISTS (SELECT 1 FROM prise_medicament p WHERE p.rappel_id = r.id
                                    AND p.date_prise = CURDATE() AND p.statut = 'pris')) AS taken
                 FROM rappel_medicament r
                 WHERE $where AND r.date_debut <= CURDATE() AND (r.date_fin IS NULL OR r.date_fin >= CURDATE())
                 GROUP BY r.user_id, r.membre_id"
            )->fetchAll();
            foreach ($rows as $row) {
                $mid = $this->ownerMemberId($visible['rappels'], $row);
                if ($mid !== null) {
                    $out[$mid]['doses_today'] = (int) $row['total'];
                    $out[$mid]['doses_taken'] = (int) $row['taken'];
                }
            }
        }
        return $out;
    }

    /** Campagnes de santé (module de Fadi) utiles à cette famille : enfants, aînés, tous */
    private function campaignsFor(array $members): array
    {
        if (!$this->hasTable('campagne_sante')) {
            return [];
        }
        $publics = ['tous'];
        foreach ($members as $m) {
            $age = $m->getAge();
            if ($age !== null && $age < 18) {
                $publics[] = 'enfants';
            }
            if ($age !== null && $age >= 60) {
                $publics[] = 'aines';
            }
        }
        $in = implode(',', array_map(fn($p) => $this->db->quote($p), array_unique($publics)));
        return $this->db->query(
            "SELECT titre, public, date_fin FROM campagne_sante
             WHERE public IN ($in) AND CURDATE() BETWEEN date_debut AND date_fin ORDER BY date_fin LIMIT 5"
        )->fetchAll();
    }

    // ==============================================================
    // ACCÈS AUX DONNÉES DE LA FAMILLE
    // ==============================================================

    private function me(): int
    {
        return (int) $_SESSION['user_id'];
    }

    private function scalar(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function scalarString(string $sql, array $params = []): string
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (string) $stmt->fetchColumn();
    }

    private function findFamily(int $id): ?FamilleModel
    {
        $stmt = $this->db->prepare('SELECT * FROM famille WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? FamilleModel::fromArray($row) : null;
    }

    private const MEMBER_SELECT = 'SELECT m.*, u.nom AS user_nom, u.avatar AS user_avatar
        FROM famille_membre m LEFT JOIN utilisateur u ON u.id = m.user_id';

    /** @return MembreModel[] gestionnaire d'abord, puis aidants, membres et profils à charge */
    private function membersOf(int $familyId): array
    {
        $stmt = $this->db->prepare(self::MEMBER_SELECT . " WHERE m.famille_id = :f
            ORDER BY FIELD(m.role, 'manager', 'caregiver', 'member'), m.user_id IS NULL, m.joined_at");
        $stmt->execute(['f' => $familyId]);
        return array_map([MembreModel::class, 'fromArray'], $stmt->fetchAll());
    }

    private function findMember(int $familyId, int $memberId): ?MembreModel
    {
        $stmt = $this->db->prepare(self::MEMBER_SELECT . ' WHERE m.famille_id = :f AND m.id = :id LIMIT 1');
        $stmt->execute(['f' => $familyId, 'id' => $memberId]);
        $row = $stmt->fetch();
        return $row ? MembreModel::fromArray($row) : null;
    }

    /**
     * CONTRÔLE D'ACCÈS : la famille existe ET le compte connecté en fait partie.
     * Sinon, même réponse dans les deux cas (on ne révèle pas quelles familles existent).
     * @return array{0: FamilleModel, 1: MembreModel}
     */
    private function requireMembership(int $familyId): array
    {
        AuthController::requireAuth();
        $family = $this->findFamily($familyId);
        $member = null;
        if ($family) {
            $stmt = $this->db->prepare(self::MEMBER_SELECT . ' WHERE m.famille_id = :f AND m.user_id = :u LIMIT 1');
            $stmt->execute(['f' => $familyId, 'u' => $this->me()]);
            $row = $stmt->fetch();
            $member = $row ? MembreModel::fromArray($row) : null;
        }
        if (!$family || !$member) {
            AuthController::setFlash('error', 'fam_err_not_found');
            $this->redirect('family');
        }
        return [$family, $member];
    }

    private function forbidden(int $familyId): never
    {
        AuthController::setFlash('error', 'fam_err_forbidden');
        $this->redirect('family_view', 'id=' . $familyId);
    }

    private function postFamilyId(): int
    {
        AuthController::requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('family');
        }
        return (int) ($_POST['family_id'] ?? 0);
    }

    /** Mes familles, avec mon rôle et le nombre de membres */
    private function familiesOf(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.id, f.nom, m.role,
                    (SELECT COUNT(*) FROM famille_membre x WHERE x.famille_id = f.id) AS nb_membres
             FROM famille f JOIN famille_membre m ON m.famille_id = f.id AND m.user_id = :u
             ORDER BY f.nom'
        );
        $stmt->execute(['u' => $userId]);
        return $stmt->fetchAll();
    }

    /** Invitations reçues, encore valables */
    private function invitationsFor(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.relation, i.expires_at, i.membre_id, f.nom AS famille, u.nom AS invite_par,
                    TIMESTAMPDIFF(MINUTE, NOW(), i.expires_at) AS minutes_left
             FROM famille_invitation i
             JOIN famille f ON f.id = i.famille_id
             JOIN utilisateur u ON u.id = i.invited_by
             WHERE i.invited_user_id = :u AND i.status = 'pending' AND i.expires_at > NOW()
             ORDER BY i.created_at DESC"
        );
        $stmt->execute(['u' => $userId]);
        return $stmt->fetchAll();
    }

    private function pendingInvitationsOf(int $familyId): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.relation, i.expires_at, i.membre_id, u.nom, TIMESTAMPDIFF(MINUTE, NOW(), i.expires_at) AS minutes_left
             FROM famille_invitation i JOIN utilisateur u ON u.id = i.invited_user_id
             WHERE i.famille_id = :f AND i.status = 'pending' AND i.expires_at > NOW() ORDER BY i.created_at DESC"
        );
        $stmt->execute(['f' => $familyId]);
        return $stmt->fetchAll();
    }

    private function messagesOf(int $familyId): array
    {
        $stmt = $this->db->prepare(
            'SELECT msg.id, msg.user_id, msg.contenu, msg.created_at, u.nom, u.avatar
             FROM famille_message msg JOIN utilisateur u ON u.id = msg.user_id
             WHERE msg.famille_id = :f ORDER BY msg.created_at DESC, msg.id DESC LIMIT 50'
        );
        $stmt->execute(['f' => $familyId]);
        return array_reverse($stmt->fetchAll()); // les plus anciens en haut, comme une discussion
    }

    /** Invitations sans réponse depuis 48 h : expirées */
    private function expireInvitations(): void
    {
        $this->db->exec("UPDATE famille_invitation SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()");
    }

    /** Secondes restantes de blocage après trop de codes faux (0 = libre) */
    private function tagLockSeconds(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS n, MIN(created_at) AS first FROM famille_tentative
             WHERE user_id = :u AND created_at > NOW() - INTERVAL ' . self::TAG_LOCK_MINUTES . ' MINUTE'
        );
        $stmt->execute(['u' => $userId]);
        $row = $stmt->fetch();
        if ((int) $row['n'] < self::TAG_MAX_ATTEMPTS) {
            return 0;
        }
        return (int) $this->scalar(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), :first + INTERVAL ' . self::TAG_LOCK_MINUTES . ' MINUTE))',
            ['first' => $row['first']]
        );
    }

    /**
     * Si le compte du gestionnaire a été supprimé, le plus ancien compte membre devient
     * gestionnaire ; une famille sans aucun compte est supprimée.
     */
    private function repairManager(int $familyId): void
    {
        if ($this->scalar("SELECT COUNT(*) FROM famille_membre WHERE famille_id = :f AND role = 'manager'", ['f' => $familyId]) > 0) {
            return;
        }
        $stmt = $this->db->prepare('SELECT id FROM famille_membre WHERE famille_id = :f AND user_id IS NOT NULL ORDER BY joined_at LIMIT 1');
        $stmt->execute(['f' => $familyId]);
        $next = $stmt->fetchColumn();
        if ($next) {
            $this->db->prepare("UPDATE famille_membre SET role = 'manager' WHERE id = :id")->execute(['id' => (int) $next]);
        } else {
            $this->db->prepare('DELETE FROM famille WHERE id = :id')->execute(['id' => $familyId]);
        }
    }

    private function memberColors(array $members): array
    {
        $colors = [];
        foreach (array_values($members) as $i => $m) {
            $colors[$m->getId()] = self::COLORS[$i % count(self::COLORS)];
        }
        return $colors;
    }

    private function sharesFromPost(array $post): array
    {
        $on = fn(string $k): int => !empty($post['share'][$k]) ? 1 : 0;
        return ['a' => $on('analyses'), 'd' => $on('dossier'), 'r' => $on('rappels'), 'v' => $on('rdv')];
    }

    private function checkCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /** Backoffice : page de views/back/pages/ dans le layout Registrar, avec l'admin connecté */
    private function renderAdmin(string $view, array $data, ?string $scripts = null): void
    {
        $auth = new AuthController();
        $data['admin'] = $auth->findById($this->me());
        $data['flash'] = AuthController::pullFlash();
        $this->render($view, $data, $scripts);
    }

    /**
     * Tables du module, créées automatiquement au premier usage (même principe que
     * password_reset). Le même schéma, commenté, est dans database.sql.
     */
    private function ensureTables(): void
    {
        if (!empty($_SESSION['family_tables_v1'])) {
            return;
        }
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS famille (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(60) NOT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_famille_user FOREIGN KEY (created_by) REFERENCES utilisateur(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS famille_membre (
                id INT AUTO_INCREMENT PRIMARY KEY,
                famille_id INT NOT NULL,
                user_id INT NULL,
                nom VARCHAR(100) NULL,
                relation VARCHAR(20) NOT NULL DEFAULT 'autre',
                date_naissance DATE NULL,
                role ENUM('manager','caregiver','member') NOT NULL DEFAULT 'member',
                share_analyses TINYINT(1) NOT NULL DEFAULT 0,
                share_dossier  TINYINT(1) NOT NULL DEFAULT 0,
                share_rappels  TINYINT(1) NOT NULL DEFAULT 0,
                share_rdv      TINYINT(1) NOT NULL DEFAULT 0,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_famille_user (famille_id, user_id),
                CONSTRAINT fk_membre_famille FOREIGN KEY (famille_id) REFERENCES famille(id) ON DELETE CASCADE,
                CONSTRAINT fk_membre_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS famille_invitation (
                id INT AUTO_INCREMENT PRIMARY KEY,
                famille_id INT NOT NULL,
                invited_by INT NOT NULL,
                invited_user_id INT NOT NULL,
                membre_id INT NULL,
                relation VARCHAR(20) NOT NULL DEFAULT 'autre',
                status ENUM('pending','accepted','refused','expired') NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                responded_at DATETIME NULL,
                CONSTRAINT fk_inv_famille FOREIGN KEY (famille_id) REFERENCES famille(id) ON DELETE CASCADE,
                CONSTRAINT fk_inv_by FOREIGN KEY (invited_by) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_inv_user FOREIGN KEY (invited_user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_inv_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS famille_message (
                id INT AUTO_INCREMENT PRIMARY KEY,
                famille_id INT NOT NULL,
                user_id INT NOT NULL,
                contenu TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_msg_famille FOREIGN KEY (famille_id) REFERENCES famille(id) ON DELETE CASCADE,
                CONSTRAINT fk_msg_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS fiche_urgence (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL UNIQUE,
                membre_id INT NULL UNIQUE,
                groupe_sanguin VARCHAR(3) NULL,
                allergies TEXT NULL,
                maladies TEXT NULL,
                traitements TEXT NULL,
                contact_urgence VARCHAR(150) NULL,
                token CHAR(32) NULL UNIQUE,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_fiche_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_fiche_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS famille_tentative (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_tentative (user_id, created_at),
                CONSTRAINT fk_tentative_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $_SESSION['family_tables_v1'] = true;
    }
}
