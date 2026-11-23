<?php
/**
 * views/front/includes/head.php
 * Contenu du <head> commun à toutes les pages du frontoffice (template Novena).
 * Les balises <head> et </head> restent dans la page appelante.
 * Variable attendue : $pageTitle (titre de l'onglet)
 */
?>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="description" content="LabTrack : plateforme web de santé pour comprendre ses analyses, organiser son dossier médical et suivre sa famille.">
<meta name="author" content="LabTrack - Esprit School of Engineering">
<title><?= e($pageTitle ?? 'LabTrack') ?></title>

<!-- Favicon -->
<link rel="icon" href="assets/front/images/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="assets/front/images/apple-touch-icon.png">

<!-- Feuilles de style du template Novena -->
<link rel="stylesheet" href="assets/front/plugins/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/front/plugins/icofont/icofont.min.css">
<link rel="stylesheet" href="assets/front/plugins/slick-carousel/slick/slick.css">
<link rel="stylesheet" href="assets/front/plugins/slick-carousel/slick/slick-theme.css">
<link rel="stylesheet" href="assets/front/css/style.css">

<!-- Personnalisation LabTrack (charte graphique, spinner, sélecteur de langue, arabe) -->
<link rel="stylesheet" href="<?= e(asset('assets/front/css/labtrack.css')) ?>">
