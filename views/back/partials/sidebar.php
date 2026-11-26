<?php
/**
 * views/back/partials/sidebar.php
 * Menu latéral du backoffice (template Registrar).
 * Partie dynamique : isActive() / ariaCurrent() activent le lien de la page courante.
 * Variables : $currentPage, $t
 */
$currentPage = $currentPage ?? 'admin';

// Icônes SVG reprises du template
$icon = [
    'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
    'users'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg>',
    'module'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',
    'site'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>',
    'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>',
];
?>
<div class="app">
<div id="sidebar" class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" aria-label="Main menu">
    <div class="sidebar-inner">
        <div class="sidebar-head">
            <a class="brand" href="index.php?page=admin" aria-label="LabTrack Admin">
                <span class="brand-mark lt-brand-mark">
                    <img src="assets/front/images/logo-icon.png" alt="" aria-hidden="true">
                </span>
                <span class="brand-text"><span class="brand-name">LabTrack</span><span class="brand-sub"><?= $t['admin_sub'] ?></span></span>
            </a>
            <button type="button" class="sidebar-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="<?= $t['close_menu'] ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>

        <nav class="sidebar-nav" aria-label="Main">
            <p class="nav-heading"><?= $t['menu_overview'] ?></p>
            <ul class="nav-list">
                <li><a class="nav-item-link <?= isActive('admin', $currentPage) ?>" href="index.php?page=admin" <?= ariaCurrent('admin', $currentPage) ?>><?= $icon['dashboard'] ?><span class="nav-label"><?= $t['admin_dashboard'] ?></span></a></li>
            </ul>

            <p class="nav-heading"><?= $t['menu_management'] ?></p>
            <ul class="nav-list">
                <li><a class="nav-item-link <?= isActive('admin_users', $currentPage) ?>" href="index.php?page=admin_users" <?= ariaCurrent('admin_users', $currentPage) ?>><?= $icon['users'] ?><span class="nav-label"><?= $t['admin_users'] ?></span></a></li>
            </ul>

            <!-- Les autres modules de LabTrack seront ajoutés ici par chaque membre du groupe -->
            <p class="nav-heading"><?= $t['menu_modules'] ?></p>
            <ul class="nav-list">
                <?php foreach (['mod_analyses', 'mod_infos', 'mod_dossier', 'mod_etsi'] as $module): ?>
                    <li><span class="nav-item-link lt-nav-soon" title="<?= $t['soon'] ?>"><?= $icon['module'] ?><span class="nav-label"><?= $t[$module] ?></span></span></li>
                <?php endforeach; ?>
                <!-- Modules disponibles -->
                <li><a class="nav-item-link <?= isActive('admin_rappels', $currentPage) ?>" href="index.php?page=admin_rappels" <?= ariaCurrent('admin_rappels', $currentPage) ?>><?= $icon['module'] ?><span class="nav-label"><?= $t['mod_rappels'] ?></span></a></li>
                <li><a class="nav-item-link <?= isActive('admin_families', $currentPage) ?>" href="index.php?page=admin_families" <?= ariaCurrent('admin_families', $currentPage) ?>><?= $icon['users'] ?><span class="nav-label"><?= $t['mod_famille'] ?></span></a></li>
            </ul>

            <p class="nav-heading"><?= $t['menu_account'] ?></p>
            <ul class="nav-list">
                <li><a class="nav-item-link" href="index.php?page=home"><?= $icon['site'] ?><span class="nav-label"><?= $t['nav_back_front'] ?></span></a></li>
                <li><a class="nav-item-link" href="index.php?page=logout"><?= $icon['logout'] ?><span class="nav-label"><?= $t['nav_logout'] ?></span></a></li>
            </ul>
        </nav>

        <div class="sidebar-foot">
            <div class="term-card">
                <p class="term-label">Esprit School of Engineering</p>
                <p class="term-week">Projet Web 2A</p>
                <p class="term-dates">2026–2027</p>
            </div>
        </div>
    </div>
</div>
