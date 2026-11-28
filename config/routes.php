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
    'forgot_password' => ['AuthController', 'forgotPasswordPage'], // mot de passe oublié : demande du code
    'reset_password'  => ['AuthController', 'resetPasswordPage'],  // code reçu (5 min) + nouveau mot de passe
    'logout'       => ['AuthController', 'logoutAction'],
    'dashboard'    => ['AuthController', 'dashboard'],
    'profile'      => ['AuthController', 'profile'],
    'set_lang'     => ['AuthController', 'changeLang'],
    'avatar'       => ['AuthController', 'avatar'],      // photo de profil (servie par PHP)

    // ---------------- RAPPELS & VISITES ----------------
    'rappels'       => ['RappelController', 'home'],              // aujourd'hui, médicaments, rendez-vous, historique (?tab=)
    'rappel_form'   => ['RappelController', 'reminderForm'],      // rappel de médicament : ajout / modification (?id=)
    'rappel_delete' => ['RappelController', 'deleteReminder'],
    'rappel_take'   => ['RappelController', 'take'],              // POST : prise cochée "pris" / "oublié"
    'rdv_form'      => ['RappelController', 'appointmentForm'],   // rendez-vous : ajout / modification (?id=)
    'rdv_status'    => ['RappelController', 'appointmentStatus'], // POST : fait / annulé / prévu
    'rdv_delete'    => ['RappelController', 'deleteAppointment'],
    'rappels_print' => ['RappelController', 'printPlan'],         // plan de prise imprimable (?owner=)
    'rappel_stock'  => ['RappelController', 'refill'],            // POST : nouveau stock d'un médicament
    'rdv_ics'       => ['RappelController', 'exportCalendar'],    // agenda .ics : ?id= ou ?all=1

    // ---------------- ESPACE FAMILLE (Ahmed) ----------------
    'family'             => ['FamilyController', 'familyHome'],       // mes familles, invitations reçues, créer
    'family_view'        => ['FamilyController', 'familyView'],       // tableau de bord (?id=&tab=)
    'family_invite'      => ['FamilyController', 'invite'],           // POST : inviter avec nom + code privé
    'family_invite_cancel' => ['FamilyController', 'cancelInvitation'],
    'family_member'      => ['FamilyController', 'memberForm'],       // profil à charge : ajout / modification
    'family_member_remove' => ['FamilyController', 'removeMember'],
    'family_role'        => ['FamilyController', 'changeRole'],
    'family_shares'      => ['FamilyController', 'updateShares'],     // consentement : ce que je partage
    'family_leave'       => ['FamilyController', 'leave'],
    'family_rename'      => ['FamilyController', 'rename'],
    'family_delete'      => ['FamilyController', 'deleteFamily'],
    'family_message'     => ['FamilyController', 'postMessage'],
    'family_message_delete' => ['FamilyController', 'deleteMessage'],
    'family_card'        => ['FamilyController', 'emergencyCard'],    // carte d'urgence + QR code
    'urgence'            => ['FamilyController', 'publicCard'],       // page ouverte par le QR code (?t=jeton)

    // ---------------- BACKOFFICE (template Registrar, rôle admin) ----------------
    'admin'        => ['AdminController', 'dashboard'],
    'admin_users'  => ['AdminController', 'users'],
    'admin_user_form' => ['AdminController', 'userForm'], // ajout (sans id) ou modification (?id=)
    'admin_role'   => ['AdminController', 'updateRole'],
    'admin_delete' => ['AdminController', 'deleteUser'],
    'admin_rappels'       => ['RappelController', 'admin'],          // rappels et rendez-vous : chiffres globaux
    'admin_families'      => ['FamilyController', 'adminFamilies'],   // familles : liste, recherche, statistiques
    'admin_family'        => ['FamilyController', 'adminFamily'],     // membres d'une famille (sans données de santé)
    'admin_family_delete' => ['FamilyController', 'adminDeleteFamily'],
    'admin_family_member_remove' => ['FamilyController', 'adminRemoveMember'],
];
