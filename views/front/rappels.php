<?php
/**
 * views/front/rappels.php
 * Module "Rappels & visites". Les onglets sont dans views/front/rappels/ :
 * today (prises et rendez-vous du jour), medicines (mes rappels), appointments
 * (rendez-vous à venir et passés), history (suivi des prises sur 30 jours).
 * Variables : $tab, $owners, $filter, $flash, $nextRdv + les données de l'onglet ouvert
 */
$tabIcons = ['today' => 'icofont-sun-alt', 'medicines' => 'icofont-pills', 'appointments' => 'icofont-calendar', 'history' => 'icofont-chart-line'];
$colors = [];
foreach (array_keys($owners) as $i => $key) {
    $colors[$key] = FamilyController::COLORS[$i % count(FamilyController::COLORS)];
}
/** Petit badge "pour qui" (seulement si je gère plusieurs personnes) */
$ownerBadge = function (string $key) use ($owners, $colors, $t): string {
    if (count($owners) < 2 || !isset($owners[$key])) {
        return '';
    }
    $o = $owners[$key];
    return '<span class="lt-owner" style="--c:' . e($colors[$key]) . '">' . e($o['self'] ? $t['rap_me'] : $o['label']) . '</span>';
};
$tabUrl = fn(string $tab): string => 'index.php?page=rappels&amp;tab=' . $tab . ($filter !== '' ? '&amp;owner=' . e($filter) : '');
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
                    <span class="text-white"><?= $t['rap_subtitle'] ?></span>
                    <h1 class="mb-5 text-lg"><?= $t['mod_rappels'] ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-5">
    <div class="container">
        <?php if ($nextRdv): $days = $nextRdv->daysLeft(); ?>
            <div class="lt-next-rdv mb-4">
                <div class="lt-next-rdv-date">
                    <strong><?= e(substr($nextRdv->getDate(), 8, 2)) ?></strong>
                    <span><?= $t['fam_month_' . (int) substr($nextRdv->getDate(), 5, 2)] ?></span>
                </div>
                <div class="flex-grow-1">
                    <small><?= $t['rap_next_rdv'] ?> <?= $ownerBadge($nextRdv->getOwnerKey()) ?></small>
                    <h4 class="mb-0"><?= e($nextRdv->getTitre()) ?></h4>
                    <span><?= e($nextRdv->getTime()) ?><?= $nextRdv->getMedecin() !== '' ? ' · ' . e($nextRdv->getMedecin()) : '' ?><?= $nextRdv->getLieu() !== '' ? ' · ' . e($nextRdv->getLieu()) : '' ?></span>
                </div>
                <span class="lt-next-rdv-badge"><?= $days === 0 ? $t['rap_today'] : ($days === 1 ? $t['rap_tomorrow'] : sprintf($t['rap_in_days'], $days)) ?></span>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap align-items-end justify-content-between lt-gap mb-2">
            <nav class="lt-fam-tabs flex-grow-1" aria-label="<?= e($t['mod_rappels']) ?>">
                <?php foreach ($tabIcons as $key => $icon): ?>
                    <a href="<?= $tabUrl($key) ?>" class="<?= $tab === $key ? 'active' : '' ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                        <i class="<?= $icon ?>"></i><span><?= $t['rap_tab_' . $key] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php if (count($owners) > 1): ?>
                <!-- Pour qui : moi et les profils à charge de mes familles -->
                <form method="get" action="index.php" class="lt-owner-filter mb-2">
                    <input type="hidden" name="page" value="rappels">
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <label for="owner" class="sr-only"><?= $t['rap_for'] ?></label>
                    <select id="owner" name="owner" class="form-control form-control-sm" data-autosubmit-select>
                        <option value=""><?= $t['rap_everyone'] ?></option>
                        <?php foreach ($owners as $key => $o): ?>
                            <option value="<?= e($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= e($o['self'] ? $t['rap_me'] : $o['label'] . ' (' . $o['family'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-sm btn-main">OK</button></noscript>
                </form>
            <?php endif; ?>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mt-3"><?= e($t[$flash['message']] ?? '') ?></div>
        <?php endif; ?>

        <div class="mt-4">
            <?php require __DIR__ . '/rappels/' . $tab . '.php'; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
