<?php
/**
 * index.php
 * Point d'entrée unique de LabTrack : chaque requête passe ici.
 * Le paramètre ?page= est cherché dans config/routes.php, qui indique
 * quel contrôleur et quelle méthode appeler.
 */
declare(strict_types=1);

// --------------------------------------------------------------
// 1. Configuration sécurisée de la session (AVANT session_start)
// --------------------------------------------------------------
ini_set('session.use_strict_mode', '1');    // refuse les identifiants de session non générés par le serveur
ini_set('session.cookie_httponly', '1');    // cookie inaccessible au JavaScript (protection XSS)
ini_set('session.cookie_samesite', 'Lax');  // limite les attaques CSRF
// Cookie "Secure" (envoyé seulement en HTTPS) activé automatiquement quand le site
// est servi en HTTPS ; en local (http://localhost) il reste désactivé.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.use_only_cookies', '1');   // l'identifiant de session ne passe jamais dans l'URL
session_start();

// --------------------------------------------------------------
// 2. Expiration de la session après 30 minutes d'inactivité
// --------------------------------------------------------------
$sessionLifetime = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionLifetime)) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// --------------------------------------------------------------
// 3. En-têtes HTTP de sécurité
// --------------------------------------------------------------
header('X-Frame-Options: DENY');                 // anti clickjacking
header('X-Content-Type-Options: nosniff');       // empêche le MIME-sniffing
header('Referrer-Policy: strict-origin-when-cross-origin');
// Le site n'utilise ni caméra, ni micro, ni géolocalisation : on les interdit
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000'); // HSTS : toujours HTTPS ensuite
}
// CSP : scripts uniquement depuis le site (aucun script inline), polices Google autorisées.
// Le QR code de la carte d'urgence est un SVG écrit dans la page : aucune source externe.
header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; "
    . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

// --------------------------------------------------------------
// 4. Chargement des classes
// --------------------------------------------------------------
require_once __DIR__ . '/views/helpers.php';
require_once __DIR__ . '/controllers/Controller.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AdminController.php';
require_once __DIR__ . '/controllers/FamilyController.php';

// Une seule connexion à la fois : ferme cette session si le compte
// s'est connecté ailleurs depuis (ou s'il a été supprimé)
AuthController::enforceSingleSession();

// Données communes à toutes les vues (textes traduits, langue, sens d'écriture, jeton CSRF)
Controller::share([
    't'         => AuthController::loadTranslations(),
    'lang'      => AuthController::getLang(),
    'dir'       => AuthController::isRtl() ? 'rtl' : 'ltr',
    'csrfToken' => AuthController::getCsrfToken(),
]);

// --------------------------------------------------------------
// 5. Routage
// --------------------------------------------------------------
$routes = require __DIR__ . '/config/routes.php';
$page = $_GET['page'] ?? 'home';

if (!isset($routes[$page])) {
    http_response_code(404);
    echo '404 - Page not found';
    exit;
}

[$controllerClass, $method] = $routes[$page];
$controller = new $controllerClass();
$controller->$method();
