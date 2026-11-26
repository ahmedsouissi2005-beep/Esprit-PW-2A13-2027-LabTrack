<?php
/**
 * views/front/user_space.php
 * Espace personnel (frontoffice) d'un patient.
 * Variables : $pageTitle, $currentPage = 'dashboard', $user (UserModel)
 */
$role = $user->getRole();
$modules = [
    ['mod_analyses', 'icofont-blood'],
    ['mod_rappels', 'icofont-pills'],
    ['mod_infos', 'icofont-newspaper'],
    ['mod_dossier', 'icofont-file-document'],
    ['mod_etsi', 'icofont-chart-growth'],
    ['mod_famille', 'icofont-users-alt-4', 'family'], // 3e valeur : page du module quand il est disponible
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

<section class="page-title bg-1">
    <div class="overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="block text-center">
                    <span class="text-white"><?= $t['welcome'] ?></span>
                    <h1 class="mb-5 text-lg"><?= e($user->getNom()) ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row">
            <!-- Carte du compte -->
            <div class="col-lg-4 mb-4">
                <div class="lt-card text-center">
                    <a href="index.php?page=profile" class="lt-avatar-picker lt-avatar-link mx-auto mb-3" title="<?= $t['photo_change'] ?>">
                        <?= avatarHtml($user) ?>
                        <span class="lt-avatar-cam" aria-hidden="true"><i class="icofont-camera"></i></span>
                    </a>
                    <h4 class="mb-1"><?= e($user->getNom()) ?></h4>
                    <p class="mb-3"><?= e($user->getEmail()) ?></p>
                    <span class="lt-badge lt-badge-<?= e($role) ?>"><?= $t['role_' . $role] ?? '' ?></span>

                    <!-- Code personnel : affiché uniquement à son propriétaire (jamais dans le backoffice) -->
                    <div class="lt-code mt-4" title="<?= e($t['code_note']) ?>">
                        <span><?= $t['your_code'] ?></span>
                        <strong>#<?= e($user->getCode()) ?></strong>
                    </div>
                    <p class="lt-code-note"><?= $t['code_note'] ?></p>

                    <ul class="list-unstyled text-left lt-info mt-3 mb-4">
                        <li><span><?= $t['language'] ?></span><strong><?= $t['lang_' . $user->getLangue()] ?? '' ?></strong></li>
                        <li><span><?= $t['member_since'] ?></span><strong><?= e(substr($user->getCreatedAt(), 0, 10)) ?></strong></li>
                    </ul>
                    <a href="index.php?page=profile" class="btn btn-main btn-round-full btn-block"><?= $t['btn_edit_profile'] ?></a>
                </div>
            </div>

            <!-- Modules LabTrack -->
            <div class="col-lg-8">
                <h2 class="title-color mb-2"><?= $t['modules_title'] ?></h2>
                <p class="mb-4"><?= $t['intro_patient'] ?></p>
                <div class="row">
                    <?php foreach ($modules as $module): [$key, $icon] = $module; $link = $module[2] ?? null; ?>
                        <div class="col-md-6 mb-4">
                            <?php if ($link): ?><a class="lt-module lt-module-link" href="index.php?page=<?= $link ?>"><?php else: ?><div class="lt-module"><?php endif; ?>
                                <i class="<?= $icon ?>"></i>
                                <div>
                                    <h5 class="mb-1"><?= $t[$key] ?></h5>
                                    <p class="mb-1"><?= $t[$key . '_desc'] ?></p>
                                    <?php if ($link): ?>
                                        <span class="lt-open"><?= $t['fam_open'] ?> <i class="icofont-simple-right"></i></span>
                                    <?php else: ?>
                                        <span class="lt-soon"><?= $t['soon'] ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php if ($link): ?></a><?php else: ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="lt-note"><i class="icofont-lock mr-1"></i><?= $t['front_note'] ?></p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
