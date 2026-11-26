<?php
/**
 * views/front/rappels/history.php - onglet "Historique"
 * Pour chaque médicament : taux de prises sur 30 jours et frise des 14 derniers jours
 * (vert = pris, rouge = oublié, gris = pas prévu). On peut rattraper un oubli en cochant
 * le jour (un clic sur la pastille). Variables : $history (+ celles de rappels.php)
 */
$planned = array_sum(array_column($history, 'planned'));
$taken = array_sum(array_column($history, 'taken'));
?>
<div class="row">
    <div class="col-lg-3 mb-4">
        <div class="lt-card lt-card-compact text-center">
            <p class="mb-1 text-muted"><?= sprintf($t['rap_history_period'], RappelController::HISTORY_DAYS) ?></p>
            <div class="lt-stat-big"><?= $planned > 0 ? round($taken * 100 / $planned) : 0 ?>%</div>
            <p class="mb-0 small"><?= sprintf($t['fam_stats_taken_of'], $taken, $planned) ?></p>
        </div>
    </div>
    <div class="col-lg-9 mb-4">
        <div class="lt-card lt-card-compact">
            <h4 class="title-color mb-1"><?= $t['rap_history_title'] ?></h4>
            <p class="small text-muted mb-4"><?= $t['rap_history_help'] ?></p>
            <?php if (!$history): ?><p class="text-muted mb-0"><?= $t['rap_no_reminders'] ?></p><?php endif; ?>
            <?php foreach ($history as $h): $r = $h['reminder'];
                $pct = $h['planned'] > 0 ? round($h['taken'] * 100 / $h['planned']) : 0; ?>
                <div class="lt-bar-row">
                    <div class="lt-bar-label">
                        <strong><?= e($r->getMedicament()) ?></strong>
                        <small class="text-muted"><?= e($r->getHeure()) ?></small>
                        <?= $ownerBadge($r->getOwnerKey()) ?>
                        <span class="ml-auto"><?= $pct ?>%</span>
                    </div>
                    <div class="lt-bar">
                        <?php if ($h['planned'] > 0): ?>
                            <span class="lt-bar-taken" style="width: <?= $h['taken'] * 100 / $h['planned'] ?>%"></span>
                            <span class="lt-bar-missed" style="width: <?= $h['missed'] * 100 / $h['planned'] ?>%"></span>
                        <?php endif; ?>
                    </div>
                    <div class="lt-days">
                        <?php foreach ($h['days'] as $day => $state): ?>
                            <?php if ($state === 'oublie' || $state === 'pris'): ?>
                                <form method="POST" action="index.php?page=rappel_take" class="lt-day-form">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= $r->getId() ?>">
                                    <input type="hidden" name="date" value="<?= e($day) ?>">
                                    <input type="hidden" name="back" value="history">
                                    <button type="submit" name="statut" value="<?= $state === 'pris' ? 'annuler' : 'pris' ?>" class="lt-day lt-day-<?= $state ?>"
                                            title="<?= e(substr($day, 8, 2) . '/' . substr($day, 5, 2) . ' · ' . $t['rap_state_' . $state] . ' · ' . ($state === 'pris' ? $t['rap_btn_undo'] : $t['rap_btn_taken'])) ?>"><?= (int) substr($day, 8, 2) ?></button>
                                </form>
                            <?php else: ?>
                                <span class="lt-day lt-day-<?= $state ?>" title="<?= e(substr($day, 8, 2) . '/' . substr($day, 5, 2)) ?>"><?= (int) substr($day, 8, 2) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted"><?= sprintf($t['fam_stats_line'], $h['taken'], $h['missed']) ?></small>
                </div>
            <?php endforeach; ?>
            <div class="lt-cal-legend mt-3">
                <span><i class="lt-bar-taken"></i><?= $t['rap_state_pris'] ?></span>
                <span><i class="lt-bar-missed"></i><?= $t['rap_state_oublie'] ?></span>
                <span><i style="background:#e4e8e5"></i><?= $t['rap_state_none'] ?></span>
            </div>
        </div>
    </div>
</div>
