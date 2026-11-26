<?php
/**
 * views/front/family/calendar.php - onglet "Calendrier"
 * Rendez-vous et heures de médicaments de toute la famille, chaque membre dans sa couleur.
 * Les données viennent du module "Rappels & visites" (tables rendez_vous et
 * rappel_medicament), seulement pour les membres qui les partagent.
 * Variables : $month (AAAA-MM), $events [jour => liste], $upcoming (+ celles de family_view.php)
 */
$first = new DateTime($month . '-01');
$prev = (clone $first)->modify('-1 month')->format('Y-m');
$next = (clone $first)->modify('+1 month')->format('Y-m');
$daysInMonth = (int) $first->format('t');
$offset = (int) $first->format('N') - 1; // lundi = première colonne
$today = date('Y-m-d');
$monthName = $t['fam_month_' . (int) $first->format('n')] . ' ' . $first->format('Y');
$names = [];
foreach ($members as $m) {
    $names[$m->getId()] = $m->getNom();
}
?>
<div class="lt-card lt-card-compact mb-4">
    <div class="lt-cal-head">
        <a class="btn btn-sm btn-outline-primary" href="<?= $baseUrl ?>&amp;tab=calendar&amp;m=<?= $prev ?>" aria-label="<?= e($t['fam_prev_month']) ?>"><i class="icofont-simple-left"></i></a>
        <h3 class="title-color mb-0"><?= e($monthName) ?></h3>
        <a class="btn btn-sm btn-outline-primary" href="<?= $baseUrl ?>&amp;tab=calendar&amp;m=<?= $next ?>" aria-label="<?= e($t['fam_next_month']) ?>"><i class="icofont-simple-right"></i></a>
    </div>

    <div class="lt-cal-legend">
        <?php foreach ($members as $m): ?>
            <span><i style="background: <?= e($colors[$m->getId()]) ?>"></i><?= e($m->getNom()) ?></span>
        <?php endforeach; ?>
        <span class="lt-cal-legend-type"><b class="lt-cal-dot-rdv"></b><?= $t['fam_cal_rdv'] ?></span>
        <span class="lt-cal-legend-type"><b class="lt-cal-dot-med"></b><?= $t['fam_cal_med'] ?></span>
    </div>

    <div class="lt-cal" role="grid" aria-label="<?= e($monthName) ?>">
        <?php for ($d = 1; $d <= 7; $d++): ?>
            <div class="lt-cal-dow" role="columnheader"><?= $t['fam_dow_' . $d] ?></div>
        <?php endfor; ?>
        <?php for ($i = 0; $i < $offset; $i++): ?>
            <div class="lt-cal-day lt-cal-empty" aria-hidden="true"></div>
        <?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++):
            $date = $first->format('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $list = $events[$date] ?? [];
            $rdvs = array_filter($list, fn($e) => $e['type'] === 'rdv');
            $meds = array_filter($list, fn($e) => $e['type'] === 'med'); ?>
            <div class="lt-cal-day <?= $date === $today ? 'is-today' : '' ?>" role="gridcell">
                <span class="lt-cal-num"><?= $day ?></span>
                <?php foreach ($rdvs as $ev): ?>
                    <span class="lt-cal-event" style="border-color: <?= e($colors[$ev['member']] ?? '#999') ?>"
                          title="<?= e(($names[$ev['member']] ?? '') . ' · ' . $ev['time'] . ' · ' . $ev['title'] . ($ev['place'] ? ' · ' . $ev['place'] : '')) ?>">
                        <b><?= e($ev['time']) ?></b> <?= e($ev['title']) ?>
                    </span>
                <?php endforeach; ?>
                <?php if ($meds): ?>
                    <span class="lt-cal-meds" title="<?= e(implode("\n", array_map(fn($e) => $e['time'] . ' · ' . ($names[$e['member']] ?? '') . ' · ' . $e['title'], $meds))) ?>">
                        <?php foreach (array_unique(array_map(fn($e) => $e['member'], $meds)) as $mid): ?><i style="background: <?= e($colors[$mid] ?? '#999') ?>"></i><?php endforeach; ?>
                        <?= sprintf($t['fam_cal_doses'], count($meds)) ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
    <?php if (!$modules['rdv'] && !$modules['rappels']): ?>
        <p class="lt-note mt-3 mb-0"><i class="icofont-plugin mr-1"></i><?= $t['fam_cal_waiting'] ?></p>
    <?php endif; ?>
</div>

<div class="lt-card lt-card-compact">
    <h4 class="title-color mb-3"><i class="icofont-ui-calendar mr-2"></i><?= $t['fam_upcoming_title'] ?></h4>
    <?php if (empty($upcoming)): ?>
        <p class="text-muted mb-0"><?= $t['fam_no_upcoming'] ?></p>
    <?php endif; ?>
    <?php foreach ($upcoming as $u): ?>
        <div class="lt-fam-line">
            <span class="lt-cal-swatch" style="background: <?= e($colors[$u['member']] ?? '#999') ?>"></span>
            <div class="lt-fam-line-text">
                <strong><?= e($u['titre']) ?></strong>
                <small class="d-block"><?= e($names[$u['member']] ?? '') ?><?= !empty($u['medecin']) ? ' · ' . e($u['medecin']) : '' ?><?= !empty($u['lieu']) ? ' · ' . e($u['lieu']) : '' ?></small>
            </div>
            <strong class="text-nowrap"><?= e(substr($u['date_rdv'], 0, 16)) ?></strong>
        </div>
    <?php endforeach; ?>
</div>
