<?php
/**
 * config/routes.php
 * Table de routage : ?page=clé  =>  [Contrôleur, méthode]
 * Pour ajouter une page : créer la vue, la méthode du contrôleur, puis une ligne ici.
 */
return [
    // ---------------- FRONTOFFICE (template Novena) ----------------
    'home'         => ['AuthController', 'home'],
    'login'        => ['AuthController', 'loginPage'],
    'register'     => ['AuthController', 'registerPage'],
    'logout'       => ['AuthController', 'logoutAction'],
    'dashboard'    => ['AuthController', 'dashboard'],
    'profile'      => ['AuthController', 'profile'],
    'set_lang'     => ['AuthController', 'changeLang'],
    'avatar'       => ['AuthController', 'avatar'],      // photo de profil (servie par PHP)

    // ---------------- BACKOFFICE (template Registrar, rôle admin) ----------------
    'admin'        => ['AdminController', 'dashboard'],
    'admin_users'  => ['AdminController', 'users'],
    'admin_user_form' => ['AdminController', 'userForm'], // ajout (sans id) ou modification (?id=)
    'admin_role'   => ['AdminController', 'updateRole'],
    'admin_delete' => ['AdminController', 'deleteUser'],
];
