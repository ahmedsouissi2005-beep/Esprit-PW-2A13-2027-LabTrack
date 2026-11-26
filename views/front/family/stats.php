<?php
/**
 * views/front/family/stats.php - onglet "Statistiques"
 * Médicaments pris / oubliés sur les 7 derniers jours, par membre (barres en CSS,
 * aucune bibliothèque). Une prise dont l'heure est passée sans être cochée "pris"
 * compte comme oubliée.
 * Variables : $stats [id membre => planned, taken, missed] (+ celles de family_view.php)
 */
$totalPlanned = array_sum(array_column($stats, 'planned'));
$totalTaken = array_sum(array_column($stats, 'taken'));
?>
<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="lt-card lt-card-compact text-center">
            <p class="mb-1 text-muted"><?= $t['fam_stats_family'] ?></p>
            <div class="lt-stat-big"><?= $totalPlanned > 0 ? round($totalTaken * 100 / $totalPlanned) : 0 ?>%</div>
            <p class="mb-0"><?= sprintf($t['fam_stats_taken_of'], $totalTaken, $totalPlanned) ?></p>
        </div>
    </div>
    <div class="col-lg-8 mb-4">
        <div class="lt-card lt-card-compact">
            <h4 class="title-color mb-1"><?= $t['fam_stats_title'] ?></h4>
            <p class="small text-muted mb-4"><?= $t['fam_stats_period'] ?></p>
            <?php if (empty($stats)): ?>
                <p class="text-muted mb-0"><?= $modules['rappels'] ? $t['fam_stats_empty'] : $t['fam_cal_waiting'] ?></p>
            <?php endif; ?>
            <?php foreach ($members as $m):
                if (!isset($stats[$m->getId()])) { continue; }
                $s = $stats[$m->getId()];
                $pct = $s['planned'] > 0 ? round($s['taken'] * 100 / $s['planned']) : 0; ?>
                <div class="lt-bar-row">
                    <div class="lt-bar-label">
                        <?= memberAvatar($m, $colors[$m->getId()], 'lt-fam-avatar-sm') ?>
                        <strong><?= e($m->getNom()) ?></strong>
                        <span class="ml-auto"><?= $pct ?>%</span>
                    </div>
                    <div class="lt-bar" role="img" aria-label="<?= e(sprintf($t['fam_stats_aria'], $m->getNom(), $s['taken'], $s['missed'])) ?>">
                        <?php if ($s['planned'] > 0): ?>
                            <span class="lt-bar-taken" style="width: <?= $s['taken'] * 100 / $s['planned'] ?>%"></span>
                            <span class="lt-bar-missed" style="width: <?= $s['missed'] * 100 / $s['planned'] ?>%"></span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted"><?= sprintf($t['fam_stats_line'], $s['taken'], $s['missed']) ?></small>
                </div>
            <?php endforeach; ?>
            <div class="lt-cal-legend mt-3">
                <span><i class="lt-bar-taken"></i><?= $t['fam_stats_taken'] ?></span>
                <span><i class="lt-bar-missed"></i><?= $t['fam_stats_missed'] ?></span>
            </div>
        </div>
    </div>
</div>
