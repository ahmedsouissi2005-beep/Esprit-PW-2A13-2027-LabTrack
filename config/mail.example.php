<?php
/**
 * config/mail.example.php
 * Modèle de configuration pour envoyer de VRAIS emails (code "mot de passe oublié").
 *
 * 1. Copier ce fichier en config/mail.local.php (ignoré par Git : jamais publié).
 * 2. Gmail : activer la validation en 2 étapes, puis créer un "mot de passe
 *    d'application" (Compte Google > Sécurité > Mots de passe des applications).
 * 3. Remplir username (adresse Gmail) et password (les 16 lettres, sans espaces).
 *
 * Sans config/mail.local.php, les emails sont enregistrés dans storage/mails/ (.eml).
 */
return [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',
    'username'   => 'votre.adresse@gmail.com',
    'password'   => 'mot-de-passe-application',
    'from_name'  => 'LabTrack',
];
