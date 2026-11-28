<?php
/**
 * views/front/includes/navbar.php
 * Barre de navigation du frontoffice (template Novena).
 * Partie dynamique : navActive() ajoute la classe "active" sur le lien
 * de la page courante, et les liens changent selon la connexion et le rôle.
 * Variables attendues : $currentPage, $t, $lang, $csrfToken
 */
$isLogged = !empty($_SESSION['user_id']);
$isAdmin  = ($_SESSION['role'] ?? '') === 'admin';
?>
<header>
    <div class="header-top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <ul class="top-bar-info list-inline-item pl-0 mb-0">
                        <li class="list-inline-item"><a href="mailto:contact@labtrack.tn"><i class="icofont-support-faq mr-2"></i>contact@labtrack.tn</a></li>
                        <li class="list-inline-item"><i class="icofont-location-pin mr-2"></i>Tunis, Tunisie</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="text-lg-right top-right-bar mt-2 mt-lg-0">
                        <!-- Sélecteur de langue : formulaire POST protégé par le jeton CSRF -->
                        <form class="lt-lang-switch" method="POST" action="index.php?page=set_lang">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="back" value="<?= e($currentPage) ?>">
                            <input type="hidden" name="back_query" value="<?= e(http_build_query(array_intersect_key($_GET, array_flip(['id', 'tab', 'member', 'm', 't', 'owner'])))) ?>">
                            <span class="lt-lang-label"><i class="icofont-globe mr-1"></i><?= $t['language'] ?> :</span>
                            <?php foreach (['fr' => 'FR', 'ar' => 'عربي', 'en' => 'EN'] as $code => $label): ?>
                                <button type="submit" name="langue" value="<?= $code ?>"
                                        class="<?= $lang === $code ? 'active' : '' ?>"
                                        title="<?= $t['lang_' . $code] ?>"><?= $label ?></button>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg navigation" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php?page=home">
                <img src="assets/front/images/logo-horizontal.png" alt="LabTrack" class="img-fluid lt-logo">
            </a>

            <button class="navbar-toggler collapsed" type="button" data-toggle="collapse" data-target="#navbarmain"
                    aria-controls="navbarmain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="icofont-navigation-menu"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarmain">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item <?= navActive('home', $currentPage) ?>"><a class="nav-link" href="index.php?page=home"><?= $t['nav_home'] ?></a></li>

                    <?php if ($isLogged): ?>
                        <li class="nav-item <?= navActive('dashboard', $currentPage) ?>"><a class="nav-link" href="index.php?page=dashboard"><?= $t['nav_dashboard'] ?></a></li>
                        <li class="nav-item <?= navActive('bilans', $currentPage) ?>"><a class="nav-link" href="index.php?page=bilans"><?= $t['bil_nav'] ?></a></li>
                        <li class="nav-item <?= navActive('rappels', $currentPage) ?>"><a class="nav-link" href="index.php?page=rappels"><?= $t['rap_nav'] ?></a></li>
                        <li class="nav-item <?= navActive('family', $currentPage) ?>"><a class="nav-link" href="index.php?page=family"><?= $t['fam_nav'] ?></a></li>
                        <li class="nav-item <?= navActive('profile', $currentPage) ?>"><a class="nav-link" href="index.php?page=profile"><?= $t['nav_profile'] ?></a></li>
                        <?php if ($isAdmin): ?>
                            <li class="nav-item"><a class="nav-link" href="index.php?page=admin"><?= $t['nav_admin'] ?></a></li>
                        <?php endif; ?>
                        <li class="nav-item lt-nav-btn"><a class="btn btn-main-2 btn-round-full btn-sm" href="index.php?page=logout"><?= $t['nav_logout'] ?></a></li>
                    <?php else: ?>
                        <li class="nav-item <?= navActive('login', $currentPage) ?>"><a class="nav-link" href="index.php?page=login"><?= $t['nav_login'] ?></a></li>
                        <li class="nav-item lt-nav-btn"><a class="btn btn-main-2 btn-round-full btn-sm" href="index.php?page=register"><?= $t['nav_register'] ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
</header>
