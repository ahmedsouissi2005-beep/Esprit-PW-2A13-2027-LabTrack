<?php
/**
 * views/front/rappels.php
 * Module "Rappels & visites". Les onglets sont dans views/front/rappels/ :
 * overview (vue d'ensemble), medicines (mes rappels), today (prises et rendez-vous du jour,
 * rappel vocal), appointments (rendez-vous à venir et passés), carnet (carnet médical),
 * history (suivi interactif des prises sur 30 jours).
 * Variables : $tab, $owners, $filter, $flash, $nextRdv + les données de l'onglet ouvert
 */
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
        <div class="lt-personal-caption"><?= e($t['rap_personal_space']) ?></div>
        <nav class="lt-fam-tabs mb-4" aria-label="<?= e($t['rap_personal_space']) ?>">
            <?php
            $spaceLinks = [
                ['overview', 'icofont-home', $t['rap_nav_overview']],
                ['medicines', 'icofont-pills', $t['rap_nav_medicines']],
                ['today', 'icofont-alarm', $t['rap_nav_today']],
                ['appointments', 'icofont-calendar', $t['rap_nav_appointments']],
                ['carnet', 'icofont-notebook', $t['rap_nav_carnet']],
                ['history', 'icofont-chart-line', $t['rap_nav_history']],
            ];
            foreach ($spaceLinks as [$key, $ico, $label]):
                $active = $tab === $key;
            ?>
                <a href="<?= $tabUrl($key) ?>" class="<?= $active ? 'active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                    <i class="<?= e($ico) ?>"></i><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($nextRdv): $days = $nextRdv->daysLeft(); ?>
            <div class="lt-personal-next">
                <div class="lt-personal-next-icon"><i class="icofont-calendar"></i></div>
                <div><small><?= e($t['rap_next_rdv']) ?></small><strong><?= e($nextRdv->getTitre()) ?></strong>
                <span><?= e($nextRdv->getDate()) ?> · <?= e($nextRdv->getTime()) ?><?= $nextRdv->getMedecin() !== '' ? ' · ' . e($nextRdv->getMedecin()) : '' ?></span></div>
                <b><?= e($days === 0 ? $t['rap_today'] : ($days === 1 ? $t['rap_tomorrow'] : sprintf($t['rap_in_days'], $days))) ?></b>
            </div>
        <?php endif; ?>
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= ($flash['type'] ?? '') === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
        <?php endif; ?>
        <?php if (count($owners) > 1): ?>
            <form method="get" action="index.php" class="lt-personal-filter">
                <input type="hidden" name="page" value="rappels"><input type="hidden" name="tab" value="<?= e($tab) ?>">
                <label for="owner"><?= e($t['rap_show_data_of']) ?></label>
                <select id="owner" name="owner" class="form-control form-control-sm" data-autosubmit-select>
                    <option value=""><?= e($t['rap_everyone']) ?></option>
                    <?php foreach ($owners as $key => $o): ?>
                        <option value="<?= e($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= e($o['self'] ? $t['rap_me'] : $o['label'] . ' (' . $o['family'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>

        <div class="lt-personal-content">
            <?php require __DIR__ . '/rappels/' . $tab . '.php'; ?>
        </div>
    </div>
</section>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
