<?php
/**
 * views/front/rappels/overview.php - onglet "Vue d'ensemble" (première page des Rappels)
 * Chiffres du jour et raccourcis vers les autres onglets.
 * Variables : $reminders, $todayDoses, $upcoming (+ celles de rappels.php)
 */
$shortcuts = [
    ['medicines', '💊', 'rap_nav_medicines', 'rap_ov_desc_medicines'],
    ['today', '🔔', 'rap_nav_today', 'rap_ov_desc_today'],
    ['appointments', '🗓️', 'rap_nav_appointments', 'rap_ov_desc_appointments'],
    ['carnet', '📝', 'rap_nav_carnet', 'rap_ov_desc_carnet'],
    ['history', '📈', 'rap_nav_history', 'rap_ov_desc_history'],
];
?>
<div class="lt-overview-hero">
  <div><span><?= e($t['rap_ov_kicker']) ?></span><h2><?= e($t['rap_subtitle']) ?></h2>
  <p><?= e($t['rap_ov_text']) ?></p>
  <a href="<?= $tabUrl('today') ?>"><?= e($t['rap_ov_cta']) ?> <b>→</b></a></div>
  <div class="lt-overview-hero-icon" aria-hidden="true">✚</div>
</div>
<div class="lt-overview-stats">
  <article><span aria-hidden="true">💊</span><small><?= e($t['rap_ov_stat_meds']) ?></small><strong><?= count($reminders) ?></strong></article>
  <article><span aria-hidden="true">⏰</span><small><?= e($t['rap_ov_stat_doses']) ?></small><strong><?= count($todayDoses) ?></strong></article>
  <article><span aria-hidden="true">🩺</span><small><?= e($t['rap_ov_stat_rdv']) ?></small><strong><?= count($upcoming) ?></strong></article>
</div>
<?php if (!empty($lowStock)): ?>
    <!-- Boîtes bientôt vides : environ RappelController::STOCK_ALERT_DAYS jours de traitement ou moins -->
    <div class="lt-stock-alert mb-4" role="status">
        <strong><i class="icofont-warning-alt mr-1"></i><?= e($t['rap_stock_alert_title']) ?></strong>
        <ul class="mb-0">
            <?php foreach ($lowStock as $ls): $lr = $ls['reminder']; $st = $ls['status']; ?>
                <li><?= e($st['out']
                    ? sprintf($t['rap_stock_alert_out'], $lr->getMedicament())
                    : sprintf($t['rap_stock_alert_line'], $lr->getMedicament(), $st['left'], $st['days'])) ?> <?= $ownerBadge($lr->getOwnerKey()) ?>
                    <a class="lt-link small" href="<?= $tabUrl('medicines') ?>#stock-<?= $lr->getId() ?>"><?= e($t['rap_btn_refill']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<div class="lt-overview-section"><h3><?= e($t['rap_ov_section']) ?></h3><p><?= e($t['rap_ov_section_text']) ?></p>
<div class="lt-overview-shortcuts">
  <?php foreach ($shortcuts as [$tabKey, $icon, $titleKey, $descKey]): ?>
    <a href="<?= $tabUrl($tabKey) ?>"><span aria-hidden="true"><?= $icon ?></span><b><?= e($t[$titleKey]) ?></b><small><?= e($t[$descKey]) ?></small></a>
  <?php endforeach; ?>
</div></div>
