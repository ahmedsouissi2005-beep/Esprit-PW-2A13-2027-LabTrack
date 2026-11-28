<?php
/**
 * views/front/rappels/history.php - onglet "Historique"
 * Historique visuel et interactif, calculé sur chaque dose individuelle : chiffres clés,
 * calendrier des 30 derniers jours, évolution quotidienne (7 / 30 jours), progression par
 * médicament et détail des prises d'un jour.
 * Le JavaScript est dans assets/js/rappel-history.js (la CSP interdit les scripts inline) :
 * les données lui arrivent dans un bloc JSON non exécutable, les textes par des data-*.
 * Variables : $history, $historyDaily (+ celles de rappels.php)
 */
$planned = array_sum(array_column($history, 'planned'));
$taken = array_sum(array_column($history, 'taken'));
$missed = max(0, $planned - $taken);
$rate = $planned > 0 ? (int) round($taken * 100 / $planned) : 0;
$historyDaily = $historyDaily ?? [];
$today = date('Y-m-d');
$todayStats = $historyDaily[$today] ?? ['planned' => 0, 'taken' => 0, 'missed' => 0, 'percent' => 0];
$dayLabel = fn(string $iso): string => (int) substr($iso, 8, 2) . ' ' . $t['fam_month_' . (int) substr($iso, 5, 2)] . ' ' . substr($iso, 0, 4);
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[] = $t['fam_month_' . $m];
}
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="lt-history-v2" id="ltHistory"
     data-today="<?= e($today) ?>"
     data-doses="<?= e($t['rap_hv_doses_count']) ?>"
     data-detail-of="<?= e($t['rap_hv_detail_of']) ?>"
     data-confirmed-of="<?= e($t['rap_hv_confirmed_of']) ?>"
     data-slot-ok="<?= e($t['rap_hv_slot_ok']) ?>"
     data-slot-pending="<?= e($t['rap_hv_slot_pending']) ?>">
  <header class="lt-hv-hero"><div><span><?= e(sprintf($t['rap_hv_kicker'], RappelController::HISTORY_DAYS)) ?></span><h2><?= e($t['rap_hv_title']) ?></h2><p><?= e($t['rap_hv_text']) ?></p></div><div class="lt-hv-hero-icon"><i class="icofont-chart-growth"></i></div></header>
  <div class="lt-hv-kpis">
    <article class="lt-hv-kpi main"><small><?= e($t['rap_hv_rate']) ?></small><strong><?= $rate ?>%</strong><span><?= e($t['rap_hv_rate_note']) ?></span><div class="lt-hv-progress"><i style="width:<?= $rate ?>%"></i></div></article>
    <article class="lt-hv-kpi"><i class="icofont-check-circled green"></i><small><?= e($t['rap_hv_confirmed']) ?></small><strong><?= (int) $taken ?></strong><span><?= e($t['rap_hv_confirmed_note']) ?></span></article>
    <article class="lt-hv-kpi"><i class="icofont-clock-time amber"></i><small><?= e($t['rap_hv_unconfirmed']) ?></small><strong><?= (int) $missed ?></strong><span><?= e($t['rap_hv_unconfirmed_note']) ?></span></article>
    <article class="lt-hv-kpi"><i class="icofont-pills blue"></i><small><?= e($t['rap_hv_treatments']) ?></small><strong><?= count($history) ?></strong><span><?= e($t['rap_hv_treatments_note']) ?></span></article>
  </div>

  <div class="lt-hv-columns">
    <section class="lt-hv-panel">
      <div class="lt-hv-heading"><div><label><?= e($t['rap_hv_explore']) ?></label><h3><?= e($t['rap_hv_calendar']) ?></h3><p><?= e($t['rap_hv_calendar_text']) ?></p></div><i class="icofont-calendar"></i></div>
      <div class="lt-hv-calendar" id="ltHvCalendar">
        <?php foreach ($historyDaily as $day => $d): $state = $d['planned'] === 0 ? 'empty' : ($d['percent'] === 100 ? 'done' : ($d['percent'] > 0 ? 'partial' : 'missed')); ?>
          <button type="button" class="lt-hv-date <?= $state ?> <?= $day === $today ? 'selected' : '' ?>" data-day="<?= e($day) ?>" aria-pressed="<?= $day === $today ? 'true' : 'false' ?>" aria-label="<?= e($dayLabel($day)) ?>"><b><?= (int) substr($day, 8, 2) ?></b><i></i></button>
        <?php endforeach; ?>
      </div>
      <div class="lt-hv-legend"><span><i class="done"></i><?= e($t['rap_hv_done']) ?></span><span><i class="partial"></i><?= e($t['rap_hv_partial']) ?></span><span><i class="missed"></i><?= e($t['rap_hv_missed']) ?></span><span><i class="empty"></i><?= e($t['rap_hv_empty']) ?></span></div>
      <div class="lt-hv-selected"><div><small><?= e($t['rap_hv_selected']) ?></small><strong id="ltHvDateLabel"><?= e($dayLabel($today)) ?></strong></div><div><b id="ltHvDayPercent"><?= (int) $todayStats['percent'] ?>%</b><small id="ltHvDayCount"><?= e(sprintf($t['rap_hv_doses_count'], (int) $todayStats['taken'], (int) $todayStats['planned'])) ?></small></div></div>
    </section>

    <section class="lt-hv-panel">
      <div class="lt-hv-heading"><div><label><?= e($t['rap_hv_analysis']) ?></label><h3><?= e($t['rap_hv_evolution']) ?></h3><p><?= e($t['rap_hv_evolution_text']) ?></p></div><i class="icofont-chart-bar-graph"></i></div>
      <div class="lt-hv-chart-tabs"><button type="button" class="active" data-days="7"><?= e($t['rap_hv_7days']) ?></button><button type="button" data-days="30"><?= e($t['rap_hv_30days']) ?></button></div>
      <div class="lt-hv-chart" id="ltHvChart">
        <?php foreach ($historyDaily as $day => $d): ?><div class="lt-hv-bar" data-day="<?= e($day) ?>"><small><?= $d['planned'] > 0 ? (int) $d['percent'] . '%' : '—' ?></small><div><i style="height:<?= (int) $d['percent'] ?>%"></i></div><span><?= e(substr($day, 8, 2) . '/' . substr($day, 5, 2)) ?></span></div><?php endforeach; ?>
      </div>
      <div class="lt-hv-chart-foot"><span><i></i> <?= e($t['rap_hv_chart_legend']) ?></span><b><?= $rate ?>% <small><?= e($t['rap_hv_global']) ?></small></b></div>
    </section>
  </div>

  <section class="lt-hv-panel">
    <div class="lt-hv-heading lt-hv-meds-head"><div><label><?= e($t['rap_hv_by_med_kicker']) ?></label><h3><?= e($t['rap_hv_by_med']) ?></h3><p><?= e($t['rap_hv_by_med_text']) ?></p></div>
      <label class="sr-only" for="ltHvFilter"><?= e($t['rap_hv_all_meds']) ?></label>
      <select id="ltHvFilter"><option value="all"><?= e($t['rap_hv_all_meds']) ?></option><?php foreach ($history as $h): ?><option value="med-<?= (int) $h['reminder']->getId() ?>"><?= e($h['reminder']->getMedicament()) ?></option><?php endforeach; ?></select></div>
    <?php if (!$history): ?><div class="lt-hv-empty"><i class="icofont-prescription"></i><strong><?= e($t['rap_hv_none_title']) ?></strong><p><?= e($t['rap_hv_none_text']) ?></p></div><?php else: ?>
      <div class="lt-hv-med-grid" id="ltHvMedGrid">
      <?php foreach ($history as $h): $r = $h['reminder']; $pct = $h['planned'] > 0 ? (int) round($h['taken'] * 100 / $h['planned']) : 0; ?>
        <article class="lt-hv-med" data-med="med-<?= (int) $r->getId() ?>" tabindex="0"><div class="lt-hv-med-top"><i class="icofont-pills"></i><b><?= $pct ?>%</b></div><h4><?= e($r->getMedicament()) ?> <?= $ownerBadge($r->getOwnerKey()) ?></h4><p><?= e($r->getDose()) ?><?= $r->getDose() !== '' ? ' · ' : '' ?><?= e(sprintf($t['rap_hv_per_day'], $r->getFrequence())) ?></p><div class="lt-hv-med-progress"><i style="width:<?= $pct ?>%"></i></div><div class="lt-hv-med-stats"><span><?= e(sprintf($t['rap_hv_med_confirmed'], (int) $h['taken'])) ?></span><span><?= e(sprintf($t['rap_hv_med_planned'], (int) $h['planned'])) ?></span></div></article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="lt-hv-panel lt-hv-details"><div class="lt-hv-heading"><div><label><?= e($t['rap_hv_daily_kicker']) ?></label><h3><?= e($t['rap_hv_daily']) ?></h3><p id="ltHvSubtitle"><?= e($t['rap_hv_daily_text']) ?></p></div><i class="icofont-list"></i></div><div id="ltHvDetails"></div><div id="ltHvNoDetails" class="lt-hv-empty" hidden><?= e($t['rap_hv_no_doses_day']) ?></div></section>
</div>
<!-- Données pour rappel-history.js : JSON non exécutable, caractères < > & ' " échappés -->
<script type="application/json" id="ltHvData"><?= json_encode([
    'months' => $months,
    'daily'  => $historyDaily,
    'meds'   => array_map(fn($h) => [
        'id'           => 'med-' . $h['reminder']->getId(),
        'name'         => $h['reminder']->getMedicament(),
        'daily'        => $h['daily'],
        'plannedHours' => $h['planned_hours'] ?? [],
        'takenHours'   => $h['taken_hours'] ?? [],
    ], $history),
], $jsonFlags) ?></script>
<script src="<?= e(asset('assets/js/rappel-history.js')) ?>" defer></script>
