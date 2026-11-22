<?php
/**
 * views/front/home.php
 * Page d'accueil (index.html de Novena), assemblée avec les includes.
 * Variables : $pageTitle, $currentPage = 'home'
 */
$isLogged = !empty($_SESSION['user_id']);
$modules = [
    ['mod_analyses', 'icofont-blood'],
    ['mod_rappels', 'icofont-pills'],
    ['mod_infos', 'icofont-newspaper'],
    ['mod_dossier', 'icofont-file-document'],
    ['mod_etsi', 'icofont-chart-growth'],
    ['mod_famille', 'icofont-users-alt-4'],
];
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $dir ?>">
<head>
    <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body id="top">
<?php require_once __DIR__ . '/includes/spinner.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<!-- Bannière -->
<section class="banner">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 col-md-12 col-xl-7">
                <div class="block">
                    <div class="divider mb-3"></div>
                    <span class="text-uppercase text-sm letter-spacing"><?= $t['hero_kicker'] ?></span>
                    <h1 class="mb-3 mt-3"><?= $t['hero_title'] ?></h1>
                    <p class="mb-4 pr-5"><?= $t['hero_text'] ?></p>
                    <div class="btn-container">
                        <?php if ($isLogged): ?>
                            <a href="index.php?page=dashboard" class="btn btn-main-2 btn-icon btn-round-full"><?= $t['hero_cta_logged'] ?> <i class="icofont-simple-right ml-2"></i></a>
                        <?php else: ?>
                            <a href="index.php?page=register" class="btn btn-main-2 btn-icon btn-round-full"><?= $t['hero_cta'] ?> <i class="icofont-simple-right ml-2"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trois points forts -->
<section class="features">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="feature-block d-lg-flex">
                    <?php foreach ([['feat1', 'icofont-test-bottle'], ['feat2', 'icofont-file-document'], ['feat3', 'icofont-users-alt-4']] as [$key, $icon]): ?>
                        <div class="feature-item mb-5 mb-lg-0">
                            <div class="feature-icon mb-4"><i class="<?= $icon ?>"></i></div>
                            <span><?= $t[$key . '_kicker'] ?></span>
                            <h4 class="mb-3"><?= $t[$key . '_title'] ?></h4>
                            <p class="mb-0"><?= $t[$key . '_text'] ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- À propos -->
<section class="section about">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-4 col-sm-6">
                <div class="about-img">
                    <img src="assets/front/images/about/img-1.jpg" alt="" class="img-fluid">
                    <img src="assets/front/images/about/img-2.jpg" alt="" class="img-fluid mt-4">
                </div>
            </div>
            <div class="col-lg-4 col-sm-6">
                <div class="about-img mt-4 mt-lg-0">
                    <img src="assets/front/images/about/img-3.jpg" alt="" class="img-fluid">
                </div>
            </div>
            <div class="col-lg-4">
                <div class="about-content pl-4 mt-4 mt-lg-0">
                    <h2 class="title-color"><?= $t['about_title'] ?></h2>
                    <p class="mt-4 mb-5"><?= $t['about_text'] ?></p>
                    <a href="index.php?page=<?= $isLogged ? 'dashboard' : 'register' ?>" class="btn btn-main-2 btn-round-full btn-icon"><?= $isLogged ? $t['hero_cta_logged'] : $t['about_cta'] ?><i class="icofont-simple-right ml-3"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Les 6 modules de LabTrack -->
<section class="section service gray-bg">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <div class="section-title">
                    <span class="text-sm letter-spacing text-uppercase lt-kicker"><?= $t['modules_kicker'] ?></span>
                    <h2><?= $t['modules_section_title'] ?></h2>
                    <div class="divider mx-auto my-4"></div>
                    <p><?= $t['modules_section_text'] ?></p>
                </div>
            </div>
        </div>
        <div class="row">
            <?php foreach ($modules as [$key, $icon]): ?>
                <div class="col-lg-4 col-md-6 col-sm-6">
                    <div class="service-item mb-4">
                        <div class="icon d-flex align-items-center">
                            <i class="<?= $icon ?> text-lg"></i>
                            <h4 class="mt-3 mb-3"><?= $t[$key] ?></h4>
                        </div>
                        <div class="content">
                            <p class="mb-4"><?= $t[$key . '_desc'] ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Appel à l'action -->
<section class="section lt-cta">
    <div class="container text-center">
        <h2 class="text-white mb-3"><?= $t['cta_title'] ?></h2>
        <p class="text-white-50 mb-4"><?= $t['cta_text'] ?></p>
        <a href="index.php?page=<?= $isLogged ? 'dashboard' : 'register' ?>" class="btn btn-main-2 btn-round-full"><?= $isLogged ? $t['hero_cta_logged'] : $t['hero_cta'] ?></a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
