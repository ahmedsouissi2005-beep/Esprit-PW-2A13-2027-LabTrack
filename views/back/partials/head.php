<?php
/**
 * views/back/partials/head.php
 * Début du document du backoffice (template Registrar) : <head> + ouverture du <body>.
 * Variables : $title, $lang, $dir
 */
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $dir ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Applique le thème clair/sombre et l'état du menu avant l'affichage (pas de script inline : CSP) -->
    <script src="assets/back/js/theme-init.js"></script>
    <title><?= e($title ?? 'LabTrack Admin') ?></title>
    <meta name="theme-color" content="#1E3A5F">
    <link rel="icon" href="assets/front/images/favicon.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/back/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/back/css/style.css">
    <!-- Personnalisation LabTrack : couleurs de la charte + arabe -->
    <link rel="stylesheet" href="<?= e(asset('assets/back/css/labtrack.css')) ?>">
</head>
<body data-page="<?= e($currentPage ?? 'admin') ?>">
<a class="skip-link" href="#main">Skip to main content</a>
