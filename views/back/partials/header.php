<?php
/**
 * views/back/partials/header.php
 * Barre du haut du backoffice (template Registrar) : menu, recherche,
 * langue, mode sombre et menu du compte. Identique sur toutes les pages.
 * Variables : $t, $lang, $csrfToken, $currentPage
 */
$adminName  = $_SESSION['nom'] ?? '';
$adminEmail = $_SESSION['email'] ?? '';
?>
<div class="app-main">
<header class="topbar">
    <button type="button" class="icon-btn d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="<?= $t['open_menu'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    <button type="button" class="icon-btn d-none d-lg-inline-flex" id="sidebarCollapse" aria-controls="sidebar" aria-expanded="true" aria-label="<?= $t['toggle_sidebar'] ?>" title="<?= $t['toggle_sidebar'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/><path d="m15 10-2 2 2 2"/></svg></button>

    <!-- Recherche rapide d'un compte (envoie vers la page Utilisateurs) -->
    <form class="topbar-search" role="search" action="index.php" method="get">
        <input type="hidden" name="page" value="admin_users">
        <label class="visually-hidden" for="globalSearch"><?= $t['search_users'] ?></label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="search" id="globalSearch" name="q" class="form-control" placeholder="<?= $t['search_users'] ?>" autocomplete="off">
        <kbd class="search-kbd" aria-hidden="true">/</kbd>
    </form>

    <div class="topbar-actions">
        <!-- Sélecteur de langue (POST + jeton CSRF) -->
        <form class="lt-lang-switch" method="POST" action="index.php?page=set_lang">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="back" value="<?= e($currentPage) ?>">
            <?php foreach (['fr' => 'FR', 'ar' => 'ع', 'en' => 'EN'] as $code => $label): ?>
                <button type="submit" name="langue" value="<?= $code ?>" class="<?= $lang === $code ? 'active' : '' ?>" title="<?= $t['lang_' . $code] ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </form>

        <button type="button" class="icon-btn" data-theme-toggle aria-pressed="false" aria-label="<?= $t['dark_mode'] ?>" title="<?= $t['dark_mode'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="ic-moon"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="ic-sun"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></button>

        <div class="dropdown">
            <button type="button" class="user-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= e($adminName) ?>">
                <span class="avatar av-2" aria-hidden="true"><?= e(initials($adminName)) ?></span>
                <span class="user-text"><span class="user-name"><?= e($adminName) ?></span><span class="user-role"><?= $t['role_admin'] ?></span></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-menu">
                <li class="user-menu-head"><span class="avatar av-2 avatar-lg" aria-hidden="true"><?= e(initials($adminName)) ?></span><span><span class="user-name"><?= e($adminName) ?></span><span class="user-role"><?= e($adminEmail) ?></span></span></li>
                <li><a class="dropdown-item" href="index.php?page=profile"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg><?= $t['nav_profile'] ?></a></li>
                <li><a class="dropdown-item" href="index.php?page=home"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg><?= $t['nav_back_front'] ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="index.php?page=logout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg><?= $t['nav_logout'] ?></a></li>
            </ul>
        </div>
    </div>
</header>
