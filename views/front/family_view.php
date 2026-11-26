<?php
/**
 * views/front/family_view.php
 * Tableau de bord d'une famille. Les onglets sont dans views/front/family/ :
 * overview (aperçu + alertes), members (membres, invitations, partages),
 * calendar (calendrier familial), stats (prises de médicaments), messages.
 * Variables : $family, $me (MembreModel du compte connecté), $members, $colors, $tab,
 *             $flash, $errors, $old, $modules + les données de l'onglet ouvert
 */
$isManager = $me->getRole() === 'manager';
$isCaring  = in_array($me->getRole(), ['manager', 'caregiver'], true);
$tabIcons = [
    'overview' => 'icofont-dashboard-web',
    'members'  => 'icofont-users-alt-4',
    'calendar' => 'icofont-calendar',
    'stats'    => 'icofont-chart-bar-graph',
    'messages' => 'icofont-chat',
];
$baseUrl = 'index.php?page=family_view&amp;id=' . $family->getId();
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
                    <span class="text-white"><?= $t['fam_title'] ?> · <?= $t['fam_role_' . $me->getRole()] ?></span>
                    <h1 class="mb-5 text-lg"><?= e($family->getNom()) ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-5">
    <div class="container">
        <p class="mb-3"><a class="lt-link" href="index.php?page=family"><i class="icofont-simple-left mr-1"></i><?= $t['fam_back'] ?></a></p>

        <nav class="lt-fam-tabs mb-4" aria-label="<?= e($family->getNom()) ?>">
            <?php foreach ($tabIcons as $key => $icon): ?>
                <a href="<?= $baseUrl ?>&amp;tab=<?= $key ?>" class="<?= $tab === $key ? 'active' : '' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                    <i class="<?= $icon ?>"></i><span><?= $t['fam_tab_' . $key] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php require __DIR__ . '/family/' . $tab . '.php'; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
