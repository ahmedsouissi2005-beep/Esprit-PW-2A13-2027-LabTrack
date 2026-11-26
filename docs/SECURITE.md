# Workshop Sessions et Sécurité : notes de validation

Ce document résume comment le module Gestion des utilisateurs applique le workshop « Sessions et Sécurité PHP ».

## Protections mises en place

| Risque | Protection | Où ? |
|---|---|---|
| Injection SQL | Requêtes préparées PDO + `ATTR_EMULATE_PREPARES = false` | `AuthController`, `config/database.php` |
| Mots de passe en clair | `password_hash()` / `password_verify()` | `register()`, `login()`, `changePassword()` |
| XSS | `e()` / `htmlspecialchars()` en sortie, validation du nom, en-tête CSP sans script inline | vues, `isValidNom()`, `index.php` |
| CSRF | Jeton par session vérifié avec `hash_equals()` sur tous les formulaires POST | `AuthController`, toutes les vues |
| Fixation de session | `session_regenerate_id(true)` après connexion et changement de mot de passe | `login()`, `changePassword()` |
| Vol de session via JS | Cookie HttpOnly + SameSite=Lax + `use_strict_mode` | `index.php` |
| Clickjacking | `X-Frame-Options: DENY` + `frame-ancestors 'none'` | `index.php` |
| Élévation de privilèges | Rôle forcé à `patient`, `requireRole('admin')` dans le constructeur d'`AdminController` (chaque page et action) | `register()`, `AdminController` |
| Redirection libre | Liste blanche des pages de retour du sélecteur de langue | `changeLang()` |
| Sessions abandonnées | Expiration après 30 minutes d'inactivité | `index.php` |
| Compte partagé / session volée | **Une seule connexion à la fois** : jeton `session_token` renouvelé à chaque connexion, vérifié à chaque requête ; l'ancienne session est fermée | `login()`, `enforceSingleSession()`, `index.php` |
| Comptes en double | Email `UNIQUE` en base + vérification avant insertion ; l'email redevient libre après suppression du compte | `database.sql`, `register()`, `adminCreateUser()` |
| Identifiant devinable | Code personnel à 4 chiffres tiré avec `random_int()`, `UNIQUE`, visible seulement par son propriétaire | `generateUniqueCode()`, `user_space.php` |
| Force brute sur le mot de passe | Après **5 échecs**, compte bloqué **15 minutes**, même avec le bon mot de passe ; compteur remis à zéro après une connexion réussie | `login()`, `recordFailedLogin()`, colonnes `failed_attempts` / `locked_until` |
| Cookie intercepté sur le réseau | Cookie `Secure` activé automatiquement en HTTPS, `use_only_cookies` (jamais d'identifiant dans l'URL), HSTS en HTTPS | `index.php` |
| Accès abusif aux capteurs | En-tête `Permissions-Policy` : caméra, micro et géolocalisation interdits | `index.php` |
| Mot de passe oublié | Code à **6 chiffres** tiré avec `random_int()`, envoyé par email et **valable 5 minutes** (`RESET_CODE_MINUTES`), expiration vérifiée avec l'horloge MySQL (`NOW()`) | `requestResetCode()`, `resetPassword()`, table `password_reset` |
| Vol du code dans la base | Le code est stocké **haché** avec `password_hash()`, comme un mot de passe | `requestResetCode()` |
| Force brute sur le code | **5 essais** maximum ; au 5e mauvais code, le code est détruit. Code **à usage unique** : supprimé dès qu'il a servi | `resetPassword()` |
| Spam d'emails | Un seul envoi par minute pour le même compte | `requestResetCode()` |
| Découverte des emails inscrits | Même réponse et même compte à rebours, que l'email existe ou non | `forgotPasswordPage()`, `resetPasswordPage()` |
| Session restée ouverte après le changement | Nouveau mot de passe → `session_token` remis à `NULL` (les sessions ouvertes ailleurs sont fermées) et blocage anti force brute levé | `resetPassword()` |
| Mot de passe SMTP publié | Identifiants email dans `config/mail.local.php`, ignoré par Git ; sans lui, les emails sont enregistrés dans `storage/mails/` (interdit d'accès) | `config/Mailer.php`, `.gitignore` |
| Espace famille : accès aux données d'une autre famille | Chaque page vérifie que la famille existe **et** que le compte connecté en fait partie (même réponse sinon), puis son rôle (gestionnaire / aidant / membre) | `FamilyController::requireMembership()` |
| Espace famille : invitation forcée | Invitation **uniquement** avec le nom + le code privé ; même message pour « code inconnu » et « nom différent » ; l'invité doit **accepter** ; invitation valable **48 h** | `inviteByTag()`, `respondInvitation()` |
| Espace famille : codes devinés | **5 codes faux en 15 minutes** bloquent les invitations 15 minutes (comme la connexion) | `tagLockSeconds()`, table `famille_tentative` |
| Espace famille : partage sans accord | Consentement par type de donnée (analyses, dossier, médicaments, rendez-vous), choisi à l'acceptation et modifiable ; les données des autres comptes ne sont lues que si elles sont partagées | `visibleOwners()`, colonnes `share_*` |
| Carte d'urgence publique (QR code) | Jeton aléatoire de 128 bits (`random_bytes(16)`), page `noindex` / `no-referrer` qui ne montre que les informations d'urgence, jeton renouvelable ou désactivable ; QR code généré en SVG côté serveur (aucun script externe, compatible CSP) | `publicCard()`, `config/QrCode.php` |
| Admin trop curieux | Le backoffice des familles montre les membres et rôles, jamais les données de santé ni le contenu des messages | `adminFamilies()`, `adminFamily()` |

## Scénarios de test

1. Inscription : redirection vers `?page=login&registered=1`, mot de passe en `$2y$...` dans phpMyAdmin.
2. Connexion patient : `?page=dashboard`. Connexion admin : `?page=admin`.
3. Mauvais mot de passe : message générique « Email ou mot de passe incorrect ».
4. Injection SQL `' OR '1'='1` dans l'email (champ passé en `type="text"` avec F12) : refusée. Même chose dans la recherche du backoffice : aucun résultat.
5. Nom `<script>alert('test')</script>` : refusé. Un compte piégé inséré dans phpMyAdmin (`INSERT INTO utilisateur (nom, email, password, role) VALUES ('<script>alert(1)</script>', 'xss@test.tn', 'x', 'patient');`) s'affiche en texte brut.
6. Patient sur `?page=admin` ou `?page=admin_users` : erreur **403**. Déconnecté sur `?page=dashboard` : renvoyé vers la connexion.
7. Formulaire envoyé sans jeton CSRF : « Jeton de sécurité invalide ».
8. Changement de langue : l'interface passe en arabe (de droite à gauche) ou en anglais, et le choix est gardé à la prochaine connexion.
9. Admin : passer un patient en administrateur (et inversement), supprimer un compte, impossible de modifier ou supprimer son propre compte.
10. Expiration : mettre `$sessionLifetime = 10` dans `index.php`, attendre 10 secondes, recharger : retour à la connexion.
11. Connexion unique : se connecter avec le même compte dans deux navigateurs ; en rechargeant le premier, il est renvoyé vers la connexion avec le message « session fermée ».
12. Email en double : créer un compte avec un email déjà utilisé : refusé. Supprimer ce compte, puis recréer un compte avec le même email : accepté, avec un nouveau code personnel.
13. Force brute : saisir 5 fois un mauvais mot de passe pour le même compte ; à la 6e tentative, le message « Trop de tentatives… réessayez dans 15 minute(s) » s'affiche, même avec le bon mot de passe. Dans phpMyAdmin, `locked_until` contient l'heure de déblocage (mettre `NULL` pour débloquer).
14. En-têtes : F12 > Réseau > une page > En-têtes de réponse : `Content-Security-Policy`, `X-Frame-Options`, `Permissions-Policy` et le cookie `PHPSESSID; HttpOnly; SameSite=Lax`.
15. Mot de passe oublié : sur la page de connexion, « Mot de passe oublié ? », saisir l'email : un code à 6 chiffres est envoyé (en local, fichier `.eml` dans `storage/mails/`). Dans phpMyAdmin, la table `password_reset` contient le code **haché** et `expires_at` = heure d'envoi + **5 minutes**. Saisir le code et un nouveau mot de passe : connexion possible avec le nouveau.
16. Expiration du code : attendre plus de 5 minutes (le compte à rebours passe au rouge) puis saisir le code : « Ce code a expiré (validité : 5 minutes) ». Pour tester plus vite, mettre `expires_at` dans le passé dans phpMyAdmin.
17. Code deviné : saisir 5 fois un mauvais code : le code est annulé, même le bon est ensuite refusé. Un code déjà utilisé ne fonctionne pas une deuxième fois.
18. Email inconnu : demander un code pour `inconnu@test.tn` : même message que pour un vrai compte, aucun email envoyé.
19. Espace famille, accès : ouvrir `?page=family_view&id=` d'une famille dont on ne fait pas partie : « Famille introuvable », aucune information affichée. Un simple membre qui envoie à la main le formulaire « retirer un membre » : rien ne change.
20. Invitation : inviter avec un bon code mais un mauvais nom, puis un mauvais code : même message « Aucun compte ne correspond ». Au 6e code faux en 15 minutes : « Trop de codes incorrects », même avec le bon code.
21. Consentement : B accepte l'invitation en cochant seulement « Médicaments » ; A ne voit pas ses rendez-vous. B coche « Rendez-vous » : ils apparaissent dans le calendrier de A.
22. QR code : créer le QR code d'une carte d'urgence, ouvrir le lien dans une fenêtre privée (sans connexion) : seule la carte s'affiche. « Désactiver » : le même lien renvoie « Carte introuvable » (404).

## Réponses aux questions de réflexion

**1. Pourquoi vérifier le rôle seulement à la connexion est dangereux ?**
HTTP est sans état : chaque requête est indépendante. Si seule la connexion vérifie le rôle, un patient peut taper directement `index.php?page=admin`. Le rôle doit être revérifié côté serveur sur chaque page sensible (`requireRole()`).

**2. Quel fichier serait tenté de mélanger logique et affichage ?**
Les vues du backoffice (`views/back/pages/dashboard.php`, `users.php`). Il serait tentant d'y écrire les requêtes SQL. Ce serait risqué (requêtes non préparées, contrôle de rôle oublié) et difficile à maintenir. Ici, les vues reçoivent des variables préparées par `AdminController`.

**3. Pourquoi `password_hash()` plutôt que `md5()` ou `sha1()` ?**
`md5` et `sha1` sont très rapides et sans sel : un attaquant teste des milliards de mots de passe par seconde et utilise des tables précalculées. `password_hash()` utilise bcrypt, volontairement lent, avec un sel aléatoire unique, et peut évoluer vers un algorithme plus fort (`PASSWORD_DEFAULT`).

**4. Que se passerait-il sans `session_regenerate_id(true)` ?**
Fixation de session : un attaquant impose à la victime un identifiant de session qu'il connaît. Après la connexion de la victime, cet identifiant devient authentifié et l'attaquant est connecté à sa place.

**5. Pourquoi les `ini_set()` avant `session_start()` ?**
Les paramètres de la session et du cookie sont appliqués au démarrage de la session, quand le cookie est envoyé. Après `session_start()`, il est trop tard et PHP affiche un avertissement.

**6. Si un attaquant copie mon cookie PHPSESSID ?**
Il est connecté avec mon compte, car le serveur ne reconnaît le visiteur que par cet identifiant. On limite le risque avec HttpOnly, HTTPS + `cookie_secure`, l'expiration après inactivité et la déconnexion qui détruit la session.

**7. Sans `requireRole('admin')` mais avec un lien caché dans le menu ?**
Un utilisateur normal y accède quand même en tapant l'URL. Cacher un lien n'est pas une sécurité : seule la vérification côté serveur protège.
