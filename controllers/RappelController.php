<?php
/**
 * controllers/RappelController.php
 * Module "Rappels & visites" de LabTrack.
 *
 *  - Médicaments : un rappel = un médicament, une dose, une fréquence (1 à 6 prises par jour)
 *    avec une heure par prise, chaque jour entre deux dates. Chaque prise (rappel + jour +
 *    heure) est cochée "pris" ou "oublié" (table prise_medicament).
 *  - Rendez-vous : médecin, laboratoire, vaccin... avec un statut (prévu, fait, annulé)
 *    et un email de rappel la veille.
 *  - Pour soi, ET pour les profils à charge de l'Espace famille (enfant, grand-parent sans
 *    compte) quand on en est le gestionnaire ou un aidant.
 *
 * Sécurité : chaque ligne est relue avec la condition "appartient à un propriétaire que je
 * gère" (impossible de modifier les données d'un autre en changeant un id), requêtes
 * préparées, CSRF sur chaque POST, validation PHP + JS.
 *
 * Les tables sont celles attendues par l'Espace famille (docs/family_modules_contract.sql) :
 * calendrier familial, statistiques et alertes se remplissent automatiquement.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Mailer.php';
require_once __DIR__ . '/../models/RappelModel.php';
require_once __DIR__ . '/../models/RendezVousModel.php';

class RappelController extends Controller
{
    /** Historique des prises : 30 derniers jours */
    public const HISTORY_DAYS = 30;

    /** Stock : alerte quand il reste ce nombre de jours de traitement (ou moins) */
    public const STOCK_ALERT_DAYS = 3;
    public const STOCK_MAX = 9999;
    public const UNITS_MAX = 10;

    /** Carnet médical : longueur maximale d'une note */
    public const NOTE_MAX = 5000;

    /** Email de rappel envoyé pour les rendez-vous des prochaines 24 heures */
    private const REMINDER_HOURS = 24;

    /** Médicament, titre, lieu, médecin : lettres, chiffres et ponctuation simple */
    private const LABEL_REGEX = "/^[\p{L}\p{N}][\p{L}\p{N}\s'’().,+\/%:-]{1,149}$/u";

    private PDO $db;
    private array $t;

    public function __construct()
    {
        $this->db = Database::getConnexion();
        $this->t  = AuthController::loadTranslations();
        // Les profils à charge viennent de l'Espace famille : ses tables doivent exister
        if (empty($_SESSION['family_tables_v1'])) {
            new FamilyController();
        }
        $this->ensureTables();
        $this->migrateDoses();
        $this->ensureCarnetTable();
        $this->ensureStockColumns();
    }

    // ==============================================================
    // PAGES DU FRONTOFFICE
    // ==============================================================

    /** Page principale : aujourd'hui, médicaments, rendez-vous, historique */
    public function home(): void
    {
        AuthController::requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['carnet_action'])) {
            $this->handleNote($_POST);
            $this->redirect('rappels', 'tab=carnet');
        }
        $owners = $this->ownersFor($this->me());
        $this->sendDueReminders($owners);

        $tabs = ['overview', 'today', 'medicines', 'appointments', 'history', 'carnet'];
        $tab = in_array($_GET['tab'] ?? '', $tabs, true) ? $_GET['tab'] : 'overview';
        $filter = isset($owners[$_GET['owner'] ?? '']) ? $_GET['owner'] : '';
        $scope = $filter !== '' ? [$filter => $owners[$filter]] : $owners;

        $data = [
            'pageTitle'   => $this->t['mod_rappels'] . ' - LabTrack',
            'currentPage' => 'rappels',
            'tab'         => $tab,
            'owners'      => $owners,
            'filter'      => $filter,
            'flash'       => AuthController::pullFlash(),
            'nextRdv'     => $this->upcomingAppointments($owners, 1)[0] ?? null,
        ];
        // Stock de chaque rappel (onglets qui l'affichent) et liste des boîtes bientôt vides
        if (in_array($tab, ['overview', 'today', 'medicines'], true)) {
            $data['stock'] = $this->stockOf($this->remindersOf($scope));
            $data['lowStock'] = array_filter($data['stock'], fn($s) => $s['status']['low']);
        }
        if ($tab === 'overview') {
            $data['reminders'] = $this->remindersOf($scope);
            $data['upcoming'] = $this->upcomingAppointments($scope, 50);
            $data['todayDoses'] = $this->dosesOfDay($scope, date('Y-m-d'));
        } elseif ($tab === 'today') {
            $data['doses'] = $this->dosesOfDay($scope, date('Y-m-d'));
            $data['todayRdv'] = array_filter($this->upcomingAppointments($scope, 20), fn(RendezVousModel $r) => $r->getDate() === date('Y-m-d'));
        } elseif ($tab === 'medicines') {
            $data['reminders'] = $this->remindersOf($scope);
        } elseif ($tab === 'appointments') {
            $query = trim((string) ($_GET['q'] ?? ''));
            $data['query'] = $query;
            $data['upcoming'] = $this->upcomingAppointments($scope, 50, $query);
            $data['past'] = $this->pastAppointments($scope, 50, $query);
        } elseif ($tab === 'carnet') {
            $stmt = $this->db->prepare('SELECT c.*, v.titre AS rdv_titre, v.date_rdv AS rdv_date, v.medecin AS rdv_medecin FROM carnet_medical c LEFT JOIN rendez_vous v ON v.id = c.rendez_vous_id WHERE c.user_id = :u ORDER BY c.created_at DESC');
            $stmt->execute(['u' => $this->me()]);
            $data['notes'] = $stmt->fetchAll();
            $rdv = $this->db->prepare("SELECT id, titre, date_rdv, medecin FROM rendez_vous WHERE user_id = :u ORDER BY date_rdv DESC LIMIT 100");
            $rdv->execute(['u' => $this->me()]);
            $data['appointmentsForNotes'] = $rdv->fetchAll();
        } else {
            $data['history'] = $this->adherence($scope, self::HISTORY_DAYS);
            $data['historyDaily'] = $this->historyDailySummary($data['history']);
        }
        $this->renderFront('rappels', $data);
    }

    /** Ajouter (sans ?id=) ou modifier un rappel de médicament */
    public function reminderForm(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        $id = (int) ($_GET['id'] ?? 0);
        $reminder = $id > 0 ? $this->findReminder($id, $owners) : null;
        if ($id > 0 && !$reminder) {
            $this->notFound();
        }

        $old = $reminder
            ? ['owner' => $reminder->getOwnerKey(), 'medicament' => $reminder->getMedicament(), 'dose' => $reminder->getDose(),
               'frequence' => $reminder->getFrequence(), 'heures' => $reminder->getHeures(),
               'date_debut' => $reminder->getDateDebut(), 'date_fin' => (string) $reminder->getDateFin(),
               'note' => $reminder->getNote(),
               // stock affiché = ce qu'il reste aujourd'hui (pas le stock de départ)
               'stock' => ($s = $this->stockOf([$reminder])[$reminder->getId()]['status'] ?? null) ? (string) $s['left'] : '',
               'unites' => (string) $reminder->getUnitesParPrise()]
            : ['owner' => (string) ($_GET['owner'] ?? 'u' . $this->me()), 'medicament' => '', 'dose' => '', 'frequence' => 1, 'heures' => ['08:00'],
               'date_debut' => date('Y-m-d'), 'date_fin' => '', 'note' => '', 'stock' => '', 'unites' => '1'];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = array_merge($old, array_map(fn($v) => is_string($v) ? trim($v) : '', array_intersect_key($_POST, array_diff_key($old, ['frequence' => 1, 'heures' => 1]))));
            // Fréquence et heures : on garde ce que la personne a saisi pour réafficher le formulaire
            $old['frequence'] = max(1, min(RappelModel::MAX_FREQUENCE, (int) ($_POST['frequence'] ?? 1)));
            $postHeures = is_array($_POST['heures'] ?? null) ? array_map(fn($h) => is_string($h) ? trim($h) : '', array_values($_POST['heures'])) : [];
            $old['heures'] = array_slice(array_pad($postHeures, $old['frequence'], ''), 0, $old['frequence']);
            $errors = $this->saveReminder($reminder, $owners, $_POST);
            if (empty($errors)) {
                AuthController::setFlash('success', $reminder ? 'rap_msg_reminder_updated' : 'rap_msg_reminder_added');
                $this->redirect('rappels', 'tab=medicines');
            }
        }

        $this->renderFront('rappel_form', [
            'pageTitle'   => ($reminder ? $this->t['rap_edit_reminder'] : $this->t['rap_add_reminder']) . ' - LabTrack',
            'currentPage' => 'rappels',
            'reminder'    => $reminder,
            'owners'      => $owners,
            'old'         => $old,
            'errors'      => $errors,
        ]);
    }

    /** POST : supprimer un rappel (et ses prises) */
    public function deleteReminder(): void
    {
        $owners = $this->postOwners();
        $reminder = $this->findReminder((int) ($_POST['id'] ?? 0), $owners);
        if ($reminder && $this->checkCsrf()) {
            $this->db->prepare('DELETE FROM rappel_medicament WHERE id = :id')->execute(['id' => $reminder->getId()]);
            AuthController::setFlash('success', 'rap_msg_reminder_deleted');
        }
        $this->redirect('rappels', 'tab=medicines');
    }

    /**
     * POST : cocher une prise "pris" ou "oublié", ou annuler la coche.
     * Une prise = un rappel + un jour + une heure (champ "heure"). heure = "all" : toutes les
     * prises du jour d'un coup (pastille de l'historique ; le jour même, seulement celles dont
     * l'heure est passée). Seulement pour aujourd'hui ou un jour passé, pendant le traitement.
     */
    public function take(): void
    {
        $owners = $this->postOwners();
        $reminder = $this->findReminder((int) ($_POST['id'] ?? 0), $owners);
        $date = (string) ($_POST['date'] ?? '');
        $statut = (string) ($_POST['statut'] ?? '');
        $heure = trim((string) ($_POST['heure'] ?? ''));
        $back = in_array($_POST['back'] ?? '', ['today', 'history'], true) ? $_POST['back'] : 'today';
        $validDate = DateTime::createFromFormat('!Y-m-d', $date);

        if ($reminder && $this->checkCsrf() && $validDate && $validDate->format('Y-m-d') === $date
            && $date <= date('Y-m-d') && $reminder->isActiveOn($date)) {
            // Les heures visées : une seule (doit être une heure du rappel) ou toutes
            $slots = [];
            if ($heure === 'all') {
                $slots = $date < date('Y-m-d') ? $reminder->getHeures()
                    : array_values(array_filter($reminder->getHeures(), fn($h) => $h <= date('H:i')));
            } elseif (in_array($heure, $reminder->getHeures(), true)) {
                $slots = [$heure];
            }
            foreach ($slots as $slot) {
                if ($statut === 'pris' || $statut === 'oublie') {
                    $stmt = $this->db->prepare(
                        'INSERT INTO prise_medicament (rappel_id, date_prise, heure, statut, coche_par) VALUES (:r, :d, :h, :s, :u)
                         ON DUPLICATE KEY UPDATE statut = VALUES(statut), coche_par = VALUES(coche_par), pris_at = NOW()'
                    );
                    $stmt->execute(['r' => $reminder->getId(), 'd' => $date, 'h' => $slot . ':00', 's' => $statut, 'u' => $this->me()]);
                } elseif ($statut === 'annuler') {
                    $this->db->prepare('DELETE FROM prise_medicament WHERE rappel_id = :r AND date_prise = :d AND heure = :h')
                        ->execute(['r' => $reminder->getId(), 'd' => $date, 'h' => $slot . ':00']);
                }
            }
        }
        $this->redirect('rappels', 'tab=' . $back);
    }

    /**
     * POST : mettre à jour le stock d'un médicament (nouvelle boîte, recomptage).
     * Le décompte des prises repart de maintenant.
     */
    public function refill(): void
    {
        $owners = $this->postOwners();
        $reminder = $this->findReminder((int) ($_POST['id'] ?? 0), $owners);
        $stock = trim((string) ($_POST['stock'] ?? ''));
        $back = ($_POST['back'] ?? '') === 'today' ? 'today' : 'medicines';
        if ($reminder && $this->checkCsrf()) {
            if (!preg_match('/^\d{1,4}$/', $stock) || (int) $stock > self::STOCK_MAX) {
                AuthController::setFlash('error', 'rap_err_stock');
            } else {
                $this->db->prepare('UPDATE rappel_medicament SET stock = :s, stock_maj = NOW() WHERE id = :id')
                    ->execute(['s' => (int) $stock, 'id' => $reminder->getId()]);
                AuthController::setFlash('success', 'rap_msg_stock_updated');
            }
        }
        $this->redirect('rappels', 'tab=' . $back);
    }

    /**
     * Export agenda (.ics, norme iCalendar RFC 5545) : un rendez-vous (?id=) ou tous les
     * rendez-vous prévus à venir (?all=1, filtre ?owner= possible). Le fichier s'ouvre dans
     * Google Agenda, Calendrier Apple ou Outlook, avec un rappel 2 heures avant.
     * Lecture seule, et seulement pour les rendez-vous que je gère.
     */
    public function exportCalendar(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        if (!empty($_GET['all'])) {
            $key = (string) ($_GET['owner'] ?? '');
            $scope = isset($owners[$key]) ? [$key => $owners[$key]] : $owners;
            $list = $this->upcomingAppointments($scope, 200);
            $filename = 'labtrack-rendez-vous.ics';
        } else {
            $rdv = $this->findAppointment((int) ($_GET['id'] ?? 0), $owners);
            if (!$rdv) {
                $this->notFound();
            }
            $list = [$rdv];
            $filename = 'labtrack-rdv-' . str_replace('-', '', $rdv->getDate()) . '.ics';
        }

        $esc = fn(string $v): string => str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $v);
        $utc = fn(string $local): string => (new DateTime($local))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//LabTrack//Rappels et visites//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
        foreach ($list as $rdv) {
            $who = $owners[$rdv->getOwnerKey()] ?? null;
            $summary = $rdv->getTitre() . ($who && !$who['self'] ? ' - ' . $who['label'] : '');
            $desc = array_filter([
                $rdv->getMedecin() !== '' ? $this->t['rap_medecin'] . ' : ' . $rdv->getMedecin() : '',
                $rdv->getNote(),
                'LabTrack',
            ]);
            $start = $rdv->getDateRdv();
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:rdv-' . $rdv->getId() . '@labtrack.tn';
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $utc($start);
            $lines[] = 'DTEND:' . $utc(date('Y-m-d H:i:s', strtotime($start . ' +1 hour')));
            $lines[] = 'SUMMARY:' . $esc($summary);
            if ($rdv->getLieu() !== '') {
                $lines[] = 'LOCATION:' . $esc($rdv->getLieu());
            }
            $lines[] = 'DESCRIPTION:' . $esc(implode("\n", $desc));
            $lines[] = 'STATUS:' . ($rdv->getStatut() === 'annule' ? 'CANCELLED' : 'CONFIRMED');
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:' . $esc($summary);
            $lines[] = 'TRIGGER:-PT2H';
            $lines[] = 'END:VALARM';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, no-store');
        echo implode("\r\n", array_map([$this, 'foldIcsLine'], $lines)) . "\r\n";
        exit;
    }

    /** iCalendar : une ligne de plus de 75 octets est coupée, la suite commence par un espace (sans couper un caractère UTF-8) */
    private function foldIcsLine(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > ($out === '' ? 75 : 74)) {
                $out .= ($out === '' ? '' : "\r\n ") . $current;
                $current = '';
            }
            $current .= $char;
        }
        return $out === '' ? $current : $out . "\r\n " . $current;
    }

    /** Ajouter ou modifier un rendez-vous */
    public function appointmentForm(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        $id = (int) ($_GET['id'] ?? 0);
        $rdv = $id > 0 ? $this->findAppointment($id, $owners) : null;
        if ($id > 0 && !$rdv) {
            $this->notFound();
        }

        $old = $rdv
            ? ['owner' => $rdv->getOwnerKey(), 'titre' => $rdv->getTitre(), 'date' => $rdv->getDate(), 'heure' => $rdv->getTime(),
               'lieu' => $rdv->getLieu(), 'medecin' => $rdv->getMedecin(), 'note' => $rdv->getNote(), 'statut' => $rdv->getStatut()]
            : ['owner' => (string) ($_GET['owner'] ?? 'u' . $this->me()), 'titre' => '', 'date' => date('Y-m-d', strtotime('+1 day')),
               'heure' => '09:00', 'lieu' => '', 'medecin' => '', 'note' => '', 'statut' => 'prevu'];
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = array_merge($old, array_map(fn($v) => is_string($v) ? trim($v) : '', array_intersect_key($_POST, $old)));
            $errors = $this->saveAppointment($rdv, $owners, $_POST);
            if (empty($errors)) {
                AuthController::setFlash('success', $rdv ? 'rap_msg_rdv_updated' : 'rap_msg_rdv_added');
                $this->redirect('rappels', 'tab=appointments');
            }
        }

        $this->renderFront('rdv_form', [
            'pageTitle'   => ($rdv ? $this->t['rap_edit_rdv'] : $this->t['rap_add_rdv']) . ' - LabTrack',
            'currentPage' => 'rappels',
            'rdv'         => $rdv,
            'owners'      => $owners,
            'old'         => $old,
            'errors'      => $errors,
        ]);
    }

    /** POST : marquer un rendez-vous fait ou annulé, ou le remettre à "prévu" */
    public function appointmentStatus(): void
    {
        $owners = $this->postOwners();
        $rdv = $this->findAppointment((int) ($_POST['id'] ?? 0), $owners);
        $statut = (string) ($_POST['statut'] ?? '');
        if ($rdv && $this->checkCsrf() && in_array($statut, RendezVousModel::STATUTS, true)) {
            $this->db->prepare('UPDATE rendez_vous SET statut = :s WHERE id = :id')->execute(['s' => $statut, 'id' => $rdv->getId()]);
            AuthController::setFlash('success', 'rap_msg_rdv_status');
        }
        $this->redirect('rappels', 'tab=' . (($_POST['back'] ?? '') === 'today' ? 'today' : 'appointments'));
    }

    /** POST : supprimer un rendez-vous */
    public function deleteAppointment(): void
    {
        $owners = $this->postOwners();
        $rdv = $this->findAppointment((int) ($_POST['id'] ?? 0), $owners);
        if ($rdv && $this->checkCsrf()) {
            $this->db->prepare('UPDATE carnet_medical SET rendez_vous_id = NULL WHERE rendez_vous_id = :id AND user_id = :u')->execute(['id' => $rdv->getId(), 'u' => $this->me()]);
            $this->db->prepare('DELETE FROM rendez_vous WHERE id = :id')->execute(['id' => $rdv->getId()]);
            AuthController::setFlash('success', 'rap_msg_rdv_deleted');
        }
        $this->redirect('rappels', 'tab=appointments');
    }

    /** Plan de prise imprimable (à coller sur le frigo ou à montrer au pharmacien) */
    public function printPlan(): void
    {
        AuthController::requireAuth();
        $owners = $this->ownersFor($this->me());
        $key = isset($owners[$_GET['owner'] ?? '']) ? $_GET['owner'] : 'u' . $this->me();
        $reminders = array_filter($this->remindersOf([$key => $owners[$key]]), fn(RappelModel $r) => $r->getDateFin() === null || $r->getDateFin() >= date('Y-m-d'));
        $this->renderFront('rappels_print', [
            'pageTitle'   => $this->t['rap_print_title'] . ' - ' . $owners[$key]['label'],
            'currentPage' => 'rappels',
            'owner'       => $owners[$key],
            'reminders'   => $reminders,
            'upcoming'    => $this->upcomingAppointments([$key => $owners[$key]], 10),
        ]);
    }

    // ==============================================================
    // BACKOFFICE (admin) : chiffres globaux, jamais le détail des traitements
    // ==============================================================
    public function admin(): void
    {
        AuthController::requireRole('admin');
        $count = fn(string $sql): int => (int) $this->db->query($sql)->fetchColumn();
        $planned = 0;
        foreach ($this->db->query("SELECT r.id, r.heure, r.heures, r.date_debut, r.date_fin FROM rappel_medicament r
                 WHERE r.date_debut <= CURDATE() AND (r.date_fin IS NULL OR r.date_fin >= CURDATE() - INTERVAL 6 DAY)")->fetchAll() as $row) {
            $planned += $this->plannedDays(RappelModel::fromArray($row + ['user_id' => null, 'membre_id' => null, 'medicament' => '']), 7);
        }
        $taken = $count("SELECT COUNT(*) FROM prise_medicament WHERE statut = 'pris' AND date_prise >= CURDATE() - INTERVAL 6 DAY");

        $users = $this->db->query(
            "SELECT u.id, u.nom, u.avatar,
                    (SELECT COUNT(*) FROM rappel_medicament r WHERE r.user_id = u.id AND (r.date_fin IS NULL OR r.date_fin >= CURDATE())) AS rappels,
                    (SELECT COUNT(*) FROM rendez_vous v WHERE v.user_id = u.id AND v.statut = 'prevu' AND v.date_rdv >= NOW()) AS rdv,
                    (SELECT MAX(p.pris_at) FROM prise_medicament p JOIN rappel_medicament r ON r.id = p.rappel_id WHERE r.user_id = u.id) AS derniere
             FROM utilisateur u
             WHERE EXISTS (SELECT 1 FROM rappel_medicament r WHERE r.user_id = u.id)
                OR EXISTS (SELECT 1 FROM rendez_vous v WHERE v.user_id = u.id)
             ORDER BY u.nom"
        )->fetchAll();

        $auth = new AuthController();
        $this->render('rappels_admin', [
            'title'       => $this->t['rap_admin_title'] . ' · LabTrack Admin',
            'heading'     => $this->t['rap_admin_title'],
            'currentPage' => 'admin_rappels',
            'admin'       => $auth->findById($this->me()),
            'flash'       => AuthController::pullFlash(),
            'users'       => $users,
            'stats'       => [
                'reminders'  => $count('SELECT COUNT(*) FROM rappel_medicament WHERE date_fin IS NULL OR date_fin >= CURDATE()'),
                'upcoming'   => $count("SELECT COUNT(*) FROM rendez_vous WHERE statut = 'prevu' AND date_rdv >= NOW()"),
                'adherence'  => $planned > 0 ? min(100, (int) round($taken * 100 / $planned)) : 0,
                'dependents' => $count('SELECT COUNT(DISTINCT membre_id) FROM rappel_medicament WHERE membre_id IS NOT NULL'),
                'sent'       => $count('SELECT COUNT(*) FROM rendez_vous WHERE rappel_envoye = 1'),
            ],
        ]);
    }

    // ==============================================================
    // LOGIQUE MÉTIER
    // ==============================================================

    /**
     * Carnet médical : ajouter ou supprimer une note du compte connecté.
     * Une note n'appartient qu'à son auteur ; elle peut être liée à un de SES rendez-vous.
     * Le message affiché est une clé de traduction (affiché dans la langue de la page).
     */
    private function handleNote(array $post): void
    {
        if (!$this->checkCsrf()) {
            AuthController::setFlash('error', 'err_csrf');
            return;
        }
        $action = (string) ($post['carnet_action'] ?? '');
        if ($action === 'add') {
            $titre = trim((string) ($post['titre'] ?? ''));
            $contenu = trim((string) ($post['contenu'] ?? ''));
            if (mb_strlen($titre) < 2 || mb_strlen($titre) > 150 || $contenu === '' || mb_strlen($contenu) > self::NOTE_MAX) {
                AuthController::setFlash('error', 'rap_err_note');
                return;
            }
            // Rendez-vous associé : seulement un des miens (sinon la note n'est liée à rien)
            $rdvId = (int) ($post['rendez_vous_id'] ?? 0);
            if ($rdvId > 0) {
                $check = $this->db->prepare('SELECT id FROM rendez_vous WHERE id = :id AND user_id = :u LIMIT 1');
                $check->execute(['id' => $rdvId, 'u' => $this->me()]);
                if (!$check->fetchColumn()) {
                    $rdvId = 0;
                }
            }
            $stmt = $this->db->prepare('INSERT INTO carnet_medical (user_id, titre, contenu, rendez_vous_id) VALUES (:u, :t, :c, :r)');
            $stmt->execute(['u' => $this->me(), 't' => $titre, 'c' => $contenu, 'r' => $rdvId ?: null]);
            AuthController::setFlash('success', 'rap_msg_note_added');
        } elseif ($action === 'delete') {
            $stmt = $this->db->prepare('DELETE FROM carnet_medical WHERE id = :id AND user_id = :u');
            $stmt->execute(['id' => (int) ($post['id'] ?? 0), 'u' => $this->me()]);
            if ($stmt->rowCount() > 0) {
                AuthController::setFlash('success', 'rap_msg_note_deleted');
            }
        }
    }

    private function saveReminder(?RappelModel $reminder, array $owners, array $post): array
    {
        if (!$this->checkCsrf()) {
            return [$this->t['err_csrf']];
        }
        $owner = (string) ($post['owner'] ?? '');
        $medicament = trim((string) ($post['medicament'] ?? ''));
        $dose = trim((string) ($post['dose'] ?? ''));
        $frequence = (int) ($post['frequence'] ?? 0);
        $heures = is_array($post['heures'] ?? null) ? array_map(fn($h) => is_string($h) ? trim($h) : '', array_values($post['heures'])) : [];
        $debut = trim((string) ($post['date_debut'] ?? ''));
        $fin = trim((string) ($post['date_fin'] ?? ''));
        $note = trim((string) ($post['note'] ?? ''));
        $stock = trim((string) ($post['stock'] ?? ''));
        $unites = trim((string) ($post['unites'] ?? '1'));
        $errors = [];

        if (!isset($owners[$owner])) {
            $errors[] = $this->t['rap_err_owner'];
        }
        if (!preg_match(self::LABEL_REGEX, $medicament) || mb_strlen($medicament) > 100) {
            $errors[] = $this->t['rap_err_medicament'];
        }
        // Stock facultatif (vide = non suivi) : 0 à 9999 unités, 1 à 10 unités par prise
        if ($stock !== '' && (!preg_match('/^\d{1,4}$/', $stock) || (int) $stock > self::STOCK_MAX)) {
            $errors[] = $this->t['rap_err_stock'];
        }
        if (!preg_match('/^\d{1,2}$/', $unites) || (int) $unites < 1 || (int) $unites > self::UNITS_MAX) {
            $errors[] = sprintf($this->t['rap_err_units'], self::UNITS_MAX);
        }
        if (mb_strlen($dose) > 50) {
            $errors[] = sprintf($this->t['rap_err_length'], $this->t['rap_dose'], 50);
        }
        // Fréquence 1 à 6, et exactement une heure valide et différente par prise
        if ($frequence < 1 || $frequence > RappelModel::MAX_FREQUENCE) {
            $errors[] = sprintf($this->t['rap_err_frequence'], RappelModel::MAX_FREQUENCE);
        } else {
            $heures = array_slice($heures, 0, $frequence);
            $valid = count($heures) === $frequence;
            foreach ($heures as $h) {
                $valid = $valid && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h) === 1;
            }
            if (!$valid) {
                $errors[] = $this->t['rap_err_times'];
            } elseif (count(array_unique($heures)) !== $frequence) {
                $errors[] = $this->t['rap_err_times_dup'];
            }
        }
        if (!$this->isDate($debut)) {
            $errors[] = $this->t['rap_err_date'];
        }
        if ($fin !== '' && (!$this->isDate($fin) || $fin < $debut)) {
            $errors[] = $this->t['rap_err_end'];
        }
        if (mb_strlen($note) > 200) {
            $errors[] = sprintf($this->t['rap_err_length'], $this->t['rap_note'], 200);
        }
        if ($errors) {
            return $errors;
        }

        sort($heures); // la colonne `heure` garde la 1re prise de la journée
        $params = [
            'u'   => $owners[$owner]['user_id'],
            'm'   => $owners[$owner]['membre_id'],
            'med' => $medicament,
            'do'  => $dose !== '' ? $dose : null,
            'h'   => $heures[0] . ':00',
            'hs'  => implode(',', $heures),
            'fq'  => $frequence,
            'db'  => $debut,
            'df'  => $fin !== '' ? $fin : null,
            'n'   => $note !== '' ? $note : null,
            'un'  => (int) $unites,
        ];
        /*
         * Stock : "reset" = nouveau stock compté à partir de maintenant ; "keep" = inchangé (on a
         * modifié autre chose, le décompte des prises continue) ; "clear" = plus suivi.
         */
        $mode = $stock === '' ? 'clear' : 'reset';
        if ($reminder && $stock !== '') {
            $current = $this->stockOf([$reminder])[$reminder->getId()]['status'] ?? null;
            if ($current !== null && (int) $stock === $current['left'] && (int) $unites === $reminder->getUnitesParPrise()) {
                $mode = 'keep';
            }
        }
        $params += ['st' => $stock === '' ? null : (int) $stock, 'mode' => $mode, 'mode2' => $mode];
        if ($reminder) {
            $stmt = $this->db->prepare(
                "UPDATE rappel_medicament SET user_id = :u, membre_id = :m, medicament = :med, dose = :do, heure = :h,
                        heures = :hs, frequence = :fq, date_debut = :db, date_fin = :df, note = :n, unites_par_prise = :un,
                        stock_maj = CASE :mode WHEN 'reset' THEN NOW() WHEN 'keep' THEN stock_maj ELSE NULL END,
                        stock = CASE :mode2 WHEN 'keep' THEN stock ELSE :st END
                 WHERE id = :id"
            );
            $stmt->execute($params + ['id' => $reminder->getId()]);
            // Une heure retirée ou changée : ses anciennes prises cochées n'ont plus de sens
            $this->db->prepare('DELETE FROM prise_medicament WHERE rappel_id = :id AND FIND_IN_SET(TIME_FORMAT(heure, \'%H:%i\'), :hs) = 0')
                ->execute(['id' => $reminder->getId(), 'hs' => implode(',', $heures)]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO rappel_medicament (user_id, membre_id, medicament, dose, heure, heures, frequence, date_debut, date_fin,
                        note, created_by, unites_par_prise, stock, stock_maj)
                 VALUES (:u, :m, :med, :do, :h, :hs, :fq, :db, :df, :n, :by, :un,
                        CASE :mode2 WHEN 'clear' THEN NULL ELSE :st END, CASE :mode WHEN 'clear' THEN NULL ELSE NOW() END)"
            );
            $stmt->execute($params + ['by' => $this->me()]);
        }
        return [];
    }

    private function saveAppointment(?RendezVousModel $rdv, array $owners, array $post): array
    {
        if (!$this->checkCsrf()) {
            return [$this->t['err_csrf']];
        }
        $owner = (string) ($post['owner'] ?? '');
        $titre = trim((string) ($post['titre'] ?? ''));
        $date = trim((string) ($post['date'] ?? ''));
        $heure = trim((string) ($post['heure'] ?? ''));
        $lieu = trim((string) ($post['lieu'] ?? ''));
        $medecin = trim((string) ($post['medecin'] ?? ''));
        $note = trim((string) ($post['note'] ?? ''));
        $statut = (string) ($post['statut'] ?? 'prevu');
        $errors = [];

        if (!isset($owners[$owner])) {
            $errors[] = $this->t['rap_err_owner'];
        }
        if (!preg_match(self::LABEL_REGEX, $titre)) {
            $errors[] = $this->t['rap_err_titre'];
        }
        if (!$this->isDate($date) || $date < date('Y-m-d', strtotime('-5 years')) || $date > date('Y-m-d', strtotime('+5 years'))) {
            $errors[] = $this->t['rap_err_date'];
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure)) {
            $errors[] = $this->t['rap_err_time'];
        }
        foreach (['lieu' => $lieu, 'medecin' => $medecin] as $field => $value) {
            if ($value !== '' && !preg_match(self::LABEL_REGEX, $value)) {
                $errors[] = sprintf($this->t['rap_err_field'], $this->t['rap_' . $field]);
            }
        }
        if (mb_strlen($note) > 500) {
            $errors[] = sprintf($this->t['rap_err_length'], $this->t['rap_note'], 500);
        }
        if (!in_array($statut, RendezVousModel::STATUTS, true)) {
            $errors[] = $this->t['rap_err_statut'];
        }
        if ($errors) {
            return $errors;
        }

        $when = $date . ' ' . $heure . ':00';
        $params = [
            'u'  => $owners[$owner]['user_id'],
            'm'  => $owners[$owner]['membre_id'],
            't'  => $titre,
            'd'  => $when,
            'l'  => $lieu !== '' ? $lieu : null,
            'me' => $medecin !== '' ? $medecin : null,
            'n'  => $note !== '' ? $note : null,
            's'  => $statut,
        ];
        if ($rdv) {
            // Date changée : le rappel par email sera renvoyé la veille de la nouvelle date
            $stmt = $this->db->prepare(
                // (MySQL applique les SET de gauche à droite : rappel_envoye est calculé avec l'ANCIENNE date)
                'UPDATE rendez_vous SET rappel_envoye = IF(date_rdv = :d2, rappel_envoye, 0), user_id = :u, membre_id = :m,
                        titre = :t, date_rdv = :d, lieu = :l, medecin = :me, note = :n, statut = :s WHERE id = :id'
            );
            $stmt->execute($params + ['d2' => $when, 'id' => $rdv->getId()]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO rendez_vous (user_id, membre_id, titre, date_rdv, lieu, medecin, note, statut, created_by)
                 VALUES (:u, :m, :t, :d, :l, :me, :n, :s, :by)'
            );
            $stmt->execute($params + ['by' => $this->me()]);
        }
        return [];
    }

    /**
     * Rappel par email des rendez-vous des prochaines 24 heures (une seule fois par rendez-vous).
     * XAMPP n'a pas de tâche planifiée : l'envoi se fait à l'ouverture de la page Rappels.
     */
    private function sendDueReminders(array $owners): void
    {
        $where = $this->ownerWhere($owners);
        $stmt = $this->db->query(
            "SELECT * FROM rendez_vous WHERE $where AND statut = 'prevu' AND rappel_envoye = 0
             AND date_rdv BETWEEN NOW() AND NOW() + INTERVAL " . self::REMINDER_HOURS . ' HOUR ORDER BY date_rdv'
        );
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return;
        }
        $me = $this->db->prepare('SELECT nom, email, langue FROM utilisateur WHERE id = :id');
        $me->execute(['id' => $this->me()]);
        $user = $me->fetch();
        $t = AuthController::translationsFor($user['langue']);
        $mailer = new Mailer();
        $mark = $this->db->prepare('UPDATE rendez_vous SET rappel_envoye = 1 WHERE id = :id');
        foreach ($rows as $row) {
            $rdv = RendezVousModel::fromArray($row);
            $who = $owners[$rdv->getOwnerKey()]['label'] ?? $user['nom'];
            $body = sprintf($t['rap_mail_body'], $user['nom'], $who, $rdv->getTitre(), $rdv->getDate(), $rdv->getTime(),
                $rdv->getLieu() !== '' ? $rdv->getLieu() : '-', $rdv->getMedecin() !== '' ? $rdv->getMedecin() : '-');
            if ($mailer->send($user['email'], sprintf($t['rap_mail_subject'], $rdv->getTitre()), $body)) {
                $mark->execute(['id' => $rdv->getId()]);
            }
        }
    }

    /** Prises prévues un jour donné, avec leur état : pris, oublié, en retard, à venir */
    private function dosesOfDay(array $owners, string $day): array
    {
        $doses = [];
        $taken = $this->takenMap($owners, $day, $day);
        $now = date('H:i');
        foreach ($this->remindersOf($owners) as $r) {
            if (!$r->isActiveOn($day)) {
                continue;
            }
            // Une ligne par prise : un médicament 3 fois par jour donne 3 lignes
            foreach ($r->getHeures() as $h) {
                $state = $taken[$r->getId() . '|' . $day . '|' . $h] ?? null;
                if ($state === null) {
                    $state = ($day < date('Y-m-d') || $h <= $now) ? 'late' : 'upcoming';
                }
                $doses[] = ['reminder' => $r, 'heure' => $h, 'state' => $state];
            }
        }
        usort($doses, fn($a, $b) => strcmp($a['heure'], $b['heure']) ?: strcmp($a['reminder']->getMedicament(), $b['reminder']->getMedicament()));
        return $doses;
    }

    /**
     * Suivi des prises sur N jours : par rappel, les prises prévues, prises, oubliées,
     * et l'état de chaque jour pour la frise (14 derniers jours) : pris (toutes les prises du
     * jour), oublié (aucune), partial (une partie), upcoming (pas encore l'heure).
     * `detail` donne "prises/prévues" de chaque jour pour l'info-bulle.
     */
    private function adherence(array $owners, int $days): array
    {
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $today = date('Y-m-d');
        $taken = $this->takenMap($owners, $from, $today);
        $out = [];
        foreach ($this->remindersOf($owners) as $r) {
            if ($r->getDateDebut() > $today || ($r->getDateFin() !== null && $r->getDateFin() < $from)) {
                continue;
            }
            $row = ['reminder' => $r, 'planned' => 0, 'taken' => 0, 'missed' => 0, 'days' => [], 'detail' => [], 'daily' => [], 'taken_hours' => [], 'planned_hours' => []];
            for ($i = $days - 1; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-$i days"));
                $state = 'none';
                $dayDue = 0;
                $dayTaken = 0;
                $dayTakenHours = [];
                $dayPlannedHours = [];
                if ($r->isActiveOn($day)) {
                    foreach ($r->getHeures() as $h) {
                        if ($day < $today || $h <= date('H:i')) {
                            $dayDue++;
                            $dayPlannedHours[] = $h;
                            if (($taken[$r->getId() . '|' . $day . '|' . $h] ?? '') === 'pris') {
                                $dayTaken++;
                                $dayTakenHours[] = $h;
                            }
                        }
                    }
                    $row['planned'] += $dayDue;
                    $row['taken'] += $dayTaken;
                    $row['missed'] += $dayDue - $dayTaken;
                    if ($dayDue === 0) {
                        $state = 'upcoming';
                    } else {
                        $state = $dayTaken === $dayDue ? 'pris' : ($dayTaken === 0 ? 'oublie' : 'partial');
                    }
                }
                $row['daily'][$day] = ['planned' => $dayDue, 'taken' => $dayTaken, 'missed' => max(0, $dayDue - $dayTaken), 'percent' => $dayDue > 0 ? (int) round($dayTaken * 100 / $dayDue) : null, 'state' => $state];
                $row['taken_hours'][$day] = $dayTakenHours;
                $row['planned_hours'][$day] = $dayPlannedHours;
                if ($i < 14) {
                    $row['days'][$day] = $state;
                    $row['detail'][$day] = $dayDue > 0 ? $dayTaken . '/' . $dayDue : '';
                }
            }
            $out[] = $row;
        }
        return $out;
    }

    /** Totaux réels par jour, calculés à partir des prises individuelles de chaque médicament. */
    private function historyDailySummary(array $history): array
    {
        $summary = [];
        for ($i = self::HISTORY_DAYS - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            $summary[$day] = ['planned' => 0, 'taken' => 0, 'missed' => 0];
        }
        foreach ($history as $row) {
            foreach (($row['daily'] ?? []) as $day => $stats) {
                if (isset($summary[$day])) {
                    $summary[$day]['planned'] += (int) $stats['planned'];
                    $summary[$day]['taken'] += (int) $stats['taken'];
                    $summary[$day]['missed'] += (int) $stats['missed'];
                }
            }
        }
        foreach ($summary as &$stats) {
            $stats['percent'] = $stats['planned'] > 0 ? (int) round($stats['taken'] * 100 / $stats['planned']) : 0;
        }
        unset($stats);
        return $summary;
    }

    /** Nombre de prises prévues (heure passée) sur les N derniers jours, toutes les heures du rappel */
    private function plannedDays(RappelModel $r, int $days): int
    {
        $n = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            if (!$r->isActiveOn($day)) {
                continue;
            }
            foreach ($r->getHeures() as $h) {
                if ($day < date('Y-m-d') || $h <= date('H:i')) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /**
     * Stock de chaque rappel suivi : [id => ['reminder' => RappelModel, 'status' => [left, days, low, out]]].
     * Une seule requête : prises cochées "pris" depuis la dernière mise à jour du stock.
     * @param RappelModel[] $reminders
     */
    private function stockOf(array $reminders): array
    {
        $tracked = array_filter($reminders, fn(RappelModel $r) => $r->getStock() !== null
            && ($r->getDateFin() === null || $r->getDateFin() >= date('Y-m-d')));
        if (!$tracked) {
            return [];
        }
        $ids = implode(',', array_map(fn(RappelModel $r) => $r->getId(), $tracked));
        $used = [];
        foreach ($this->db->query(
            "SELECT p.rappel_id, COUNT(*) AS n FROM prise_medicament p JOIN rappel_medicament r ON r.id = p.rappel_id
             WHERE r.id IN ($ids) AND p.statut = 'pris' AND r.stock_maj IS NOT NULL AND p.pris_at >= r.stock_maj
             GROUP BY p.rappel_id"
        )->fetchAll() as $row) {
            $used[(int) $row['rappel_id']] = (int) $row['n'];
        }
        $out = [];
        foreach ($tracked as $r) {
            $out[$r->getId()] = ['reminder' => $r, 'status' => $r->stockStatus($used[$r->getId()] ?? 0, self::STOCK_ALERT_DAYS)];
        }
        return $out;
    }

    /** [rappel_id|date|HH:MM => statut] des prises cochées entre deux dates */
    private function takenMap(array $owners, string $from, string $to): array
    {
        $where = $this->ownerWhere($owners, 'r');
        $stmt = $this->db->prepare(
            "SELECT p.rappel_id, p.date_prise, p.heure, p.statut FROM prise_medicament p
             JOIN rappel_medicament r ON r.id = p.rappel_id
             WHERE $where AND p.date_prise BETWEEN :a AND :b"
        );
        $stmt->execute(['a' => $from, 'b' => $to]);
        $map = [];
        foreach ($stmt->fetchAll() as $p) {
            $map[$p['rappel_id'] . '|' . $p['date_prise'] . '|' . substr((string) $p['heure'], 0, 5)] = $p['statut'];
        }
        return $map;
    }

    // ==============================================================
    // ACCÈS AUX DONNÉES
    // ==============================================================

    /**
     * Les personnes dont je gère les rappels : moi, et les profils à charge des familles
     * où je suis gestionnaire ou aidant. Clé "u<id>" pour moi, "m<id>" pour un profil.
     */
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

    /** @return RappelModel[] */
    private function remindersOf(array $owners): array
    {
        $where = $this->ownerWhere($owners);
        $rows = $this->db->query("SELECT * FROM rappel_medicament WHERE $where ORDER BY heure, medicament")->fetchAll();
        return array_map([RappelModel::class, 'fromArray'], $rows);
    }

    /** Un rappel, SEULEMENT s'il appartient à quelqu'un que je gère (sinon null) */
    private function findReminder(int $id, array $owners): ?RappelModel
    {
        $stmt = $this->db->prepare('SELECT * FROM rappel_medicament WHERE id = :id AND ' . $this->ownerWhere($owners) . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? RappelModel::fromArray($row) : null;
    }

    private function findAppointment(int $id, array $owners): ?RendezVousModel
    {
        $stmt = $this->db->prepare('SELECT * FROM rendez_vous WHERE id = :id AND ' . $this->ownerWhere($owners) . ' LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? RendezVousModel::fromArray($row) : null;
    }

    /** @return RendezVousModel[] rendez-vous prévus à venir (recherche facultative) */
    private function upcomingAppointments(array $owners, int $limit, string $query = ''): array
    {
        return $this->appointments($owners, "statut = 'prevu' AND date_rdv >= NOW()", 'date_rdv ASC', $limit, $query);
    }

    /** @return RendezVousModel[] rendez-vous passés, faits ou annulés */
    private function pastAppointments(array $owners, int $limit, string $query = ''): array
    {
        return $this->appointments($owners, "(statut <> 'prevu' OR date_rdv < NOW())", 'date_rdv DESC', $limit, $query);
    }

    private function appointments(array $owners, string $condition, string $order, int $limit, string $query): array
    {
        $sql = 'SELECT * FROM rendez_vous WHERE ' . $this->ownerWhere($owners) . " AND $condition";
        $params = [];
        if ($query !== '') {
            $sql .= ' AND (titre LIKE :q1 OR medecin LIKE :q2 OR lieu LIKE :q3)';
            $like = '%' . $query . '%';
            $params = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $stmt = $this->db->prepare($sql . " ORDER BY $order LIMIT " . (int) $limit);
        $stmt->execute($params);
        return array_map([RendezVousModel::class, 'fromArray'], $stmt->fetchAll());
    }

    private function me(): int
    {
        return (int) $_SESSION['user_id'];
    }

    private function postOwners(): array
    {
        AuthController::requireAuth();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('rappels');
        }
        return $this->ownersFor($this->me());
    }

    private function notFound(): never
    {
        AuthController::setFlash('error', 'rap_err_not_found');
        $this->redirect('rappels');
    }

    private function isDate(string $value): bool
    {
        $d = DateTime::createFromFormat('!Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }

    private function checkCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Tables du module (créées au premier usage, même schéma commenté dans database.sql).
     * Si elles ont été créées avant (fichier docs/family_modules_contract.sql), les colonnes
     * propres au module sont ajoutées.
     */
    private function ensureTables(): void
    {
        if (!empty($_SESSION['rappel_tables_v1'])) {
            return;
        }
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS rendez_vous (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                membre_id INT NULL,
                titre VARCHAR(150) NOT NULL,
                date_rdv DATETIME NOT NULL,
                lieu VARCHAR(150) NULL,
                medecin VARCHAR(100) NULL,
                note VARCHAR(500) NULL,
                statut ENUM('prevu','fait','annule') NOT NULL DEFAULT 'prevu',
                rappel_envoye TINYINT(1) NOT NULL DEFAULT 0,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rdv_date (date_rdv),
                CONSTRAINT fk_rdv_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_rdv_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS rappel_medicament (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                membre_id INT NULL,
                medicament VARCHAR(100) NOT NULL,
                dose VARCHAR(50) NULL,
                heure TIME NOT NULL,
                heures VARCHAR(60) NULL,
                frequence TINYINT NOT NULL DEFAULT 1,
                date_debut DATE NOT NULL,
                date_fin DATE NULL,
                note VARCHAR(200) NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_rappel_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
                CONSTRAINT fk_rappel_membre FOREIGN KEY (membre_id) REFERENCES famille_membre(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS prise_medicament (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rappel_id INT NOT NULL,
                date_prise DATE NOT NULL,
                heure TIME NOT NULL DEFAULT '00:00:00',
                statut ENUM('pris','oublie') NOT NULL DEFAULT 'pris',
                pris_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                coche_par INT NULL,
                UNIQUE KEY uq_prise (rappel_id, date_prise, heure),
                CONSTRAINT fk_prise_rappel FOREIGN KEY (rappel_id) REFERENCES rappel_medicament(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        // Tables créées avec le fichier de contrat : on ajoute les colonnes du module (MariaDB)
        $this->db->exec("ALTER TABLE rendez_vous
            ADD COLUMN IF NOT EXISTS note VARCHAR(500) NULL,
            ADD COLUMN IF NOT EXISTS statut ENUM('prevu','fait','annule') NOT NULL DEFAULT 'prevu',
            ADD COLUMN IF NOT EXISTS rappel_envoye TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN IF NOT EXISTS created_by INT NULL,
            ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        $this->db->exec("ALTER TABLE rappel_medicament
            ADD COLUMN IF NOT EXISTS note VARCHAR(200) NULL,
            ADD COLUMN IF NOT EXISTS created_by INT NULL,
            ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        $this->db->exec("ALTER TABLE prise_medicament
            ADD COLUMN IF NOT EXISTS pris_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN IF NOT EXISTS coche_par INT NULL");
        $_SESSION['rappel_tables_v1'] = true;
    }

    /** Colonnes du stock de médicaments (ajoutées une seule fois par session, MariaDB) */
    private function ensureStockColumns(): void
    {
        if (!empty($_SESSION['rappel_stock_v1'])) {
            return;
        }
        $this->db->exec('ALTER TABLE rappel_medicament
            ADD COLUMN IF NOT EXISTS stock SMALLINT UNSIGNED NULL,
            ADD COLUMN IF NOT EXISTS unites_par_prise TINYINT UNSIGNED NOT NULL DEFAULT 1,
            ADD COLUMN IF NOT EXISTS stock_maj DATETIME NULL');
        $_SESSION['rappel_stock_v1'] = true;
    }

    /**
     * Table du carnet médical (une seule fois par session, même schéma dans database.sql).
     * Séparée de ensureTables() : les sessions déjà ouvertes avant l'ajout du carnet la créent aussi.
     */
    private function ensureCarnetTable(): void
    {
        if (!empty($_SESSION['rappel_carnet_v1'])) {
            return;
        }
        $this->db->exec("CREATE TABLE IF NOT EXISTS carnet_medical (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            titre VARCHAR(150) NOT NULL,
            contenu TEXT NOT NULL,
            rendez_vous_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_carnet_user (user_id),
            CONSTRAINT fk_carnet_user FOREIGN KEY (user_id) REFERENCES utilisateur(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->exec('ALTER TABLE carnet_medical ADD COLUMN IF NOT EXISTS rendez_vous_id INT NULL');
        $_SESSION['rappel_carnet_v1'] = true;
    }
    /**
     * Passage à "plusieurs prises par jour" (une seule fois par session, relançable sans risque) :
     *  - rappel_medicament reçoit `heures` (liste des heures) et `frequence` ;
     *  - prise_medicament reçoit `heure` et sa clé unique devient (rappel, jour, heure).
     * Les anciennes lignes sont conservées : 1 prise par jour à l'ancienne `heure`.
     */
    private function migrateDoses(): void
    {
        if (!empty($_SESSION['rappel_doses_v2'])) {
            return;
        }
        $hasColumn = function (string $table, string $column): bool {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c');
            $stmt->execute(['t' => $table, 'c' => $column]);
            return (int) $stmt->fetchColumn() > 0;
        };
        if (!$hasColumn('rappel_medicament', 'heures')) {
            $this->db->exec('ALTER TABLE rappel_medicament ADD COLUMN heures VARCHAR(60) NULL AFTER heure, ADD COLUMN frequence TINYINT NOT NULL DEFAULT 1 AFTER heures');
            $this->db->exec("UPDATE rappel_medicament SET heures = TIME_FORMAT(heure, '%H:%i'), frequence = 1 WHERE heures IS NULL");
        }
        if (!$hasColumn('prise_medicament', 'heure')) {
            $this->db->exec("ALTER TABLE prise_medicament ADD COLUMN heure TIME NOT NULL DEFAULT '00:00:00' AFTER date_prise");
            // Les prises déjà cochées prennent l'heure de leur rappel
            $this->db->exec('UPDATE prise_medicament p JOIN rappel_medicament r ON r.id = p.rappel_id SET p.heure = r.heure');
            // Nouvelle clé unique, dans la même commande (la clé étrangère sur rappel_id garde un index)
            $this->db->exec('ALTER TABLE prise_medicament DROP INDEX uq_prise, ADD UNIQUE KEY uq_prise (rappel_id, date_prise, heure)');
        }
        $_SESSION['rappel_doses_v2'] = true;
    }
}
