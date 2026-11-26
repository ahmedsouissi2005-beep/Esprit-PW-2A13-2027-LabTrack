<?php
/**
 * views/front/rappels/today.php - onglet "Aujourd'hui"
 * Les prises du jour dans l'ordre des heures (pris, oublié, en retard, à venir)
 * avec les boutons pour les cocher, et les rendez-vous du jour.
 * Variables : $doses [reminder, state], $todayRdv (+ celles de rappels.php)
 */
$taken = count(array_filter($doses, fn($d) => $d['state'] === 'pris'));
$stateIcon = ['pris' => 'icofont-check-circled', 'oublie' => 'icofont-close-circled', 'late' => 'icofont-clock-time', 'upcoming' => 'icofont-hour-glass'];
?>
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="lt-card lt-card-compact">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h4 class="title-color mb-2"><i class="icofont-pills mr-2"></i><?= $t['rap_today_doses'] ?> <small class="text-muted"><?= date('d/m/Y') ?></small></h4>
                <?php if ($doses): ?><span class="lt-progress-pill"><?= sprintf($t['rap_taken_count'], $taken, count($doses)) ?></span><?php endif; ?>
            </div>
            <?php if (!$doses): ?>
                <div class="lt-empty">
                    <i class="icofont-pills"></i>
                    <p class="mb-3"><?= $t['rap_no_doses'] ?></p>
                    <a class="btn btn-main btn-round-full btn-sm" href="index.php?page=rappel_form"><?= $t['rap_add_reminder'] ?></a>
                </div>
            <?php endif; ?>
            <?php foreach ($doses as $d): $r = $d['reminder']; ?>
                <div class="lt-dose lt-dose-<?= e($d['state']) ?>">
                    <span class="lt-dose-time"><?= e($r->getHeure()) ?></span>
                    <div class="lt-dose-text">
                        <strong><?= e($r->getMedicament()) ?></strong><?= $r->getDose() !== '' ? ' · ' . e($r->getDose()) : '' ?> <?= $ownerBadge($r->getOwnerKey()) ?>
                        <?php if ($r->getNote() !== ''): ?><small class="d-block text-muted"><?= e($r->getNote()) ?></small><?php endif; ?>
                    </div>
                    <span class="lt-dose-state"><i class="<?= $stateIcon[$d['state']] ?>"></i> <?= $t['rap_state_' . $d['state']] ?></span>
                    <form class="lt-inline-form" method="POST" action="index.php?page=rappel_take">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= $r->getId() ?>">
                        <input type="hidden" name="date" value="<?= date('Y-m-d') ?>">
                        <input type="hidden" name="back" value="today">
                        <?php if (in_array($d['state'], ['pris', 'oublie'], true)): ?>
                            <button type="submit" name="statut" value="annuler" class="btn btn-sm btn-link lt-undo"><?= $t['rap_btn_undo'] ?></button>
                        <?php else: ?>
                            <button type="submit" name="statut" value="pris" class="btn btn-sm btn-main btn-round-full"><i class="icofont-check"></i> <?= $t['rap_btn_taken'] ?></button>
                            <button type="submit" name="statut" value="oublie" class="btn btn-sm btn-outline-secondary btn-round-full"><?= $t['rap_btn_missed'] ?></button>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="lt-card lt-card-compact mb-4">
            <h4 class="title-color mb-3"><i class="icofont-stethoscope-alt mr-2"></i><?= $t['rap_today_rdv'] ?></h4>
            <?php if (!$todayRdv): ?><p class="text-muted mb-0"><?= $t['rap_no_rdv_today'] ?></p><?php endif; ?>
            <?php foreach ($todayRdv as $rdv): ?>
                <div class="lt-fam-line">
                    <span class="lt-dose-time"><?= e($rdv->getTime()) ?></span>
                    <div class="lt-fam-line-text">
                        <strong><?= e($rdv->getTitre()) ?></strong> <?= $ownerBadge($rdv->getOwnerKey()) ?>
                        <small class="d-block"><?= e(trim($rdv->getMedecin() . ' · ' . $rdv->getLieu(), ' ·')) ?></small>
                    </div>
                    <form method="POST" action="index.php?page=rdv_status">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= $rdv->getId() ?>">
                        <input type="hidden" name="back" value="today">
                        <button type="submit" name="statut" value="fait" class="btn btn-sm btn-outline-success" title="<?= e($t['rap_btn_done']) ?>"><i class="icofont-check"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="lt-fam-howto">
            <h6 class="title-color"><i class="icofont-light-bulb mr-1"></i><?= $t['rap_tip_title'] ?></h6>
            <p class="mb-2 small"><?= $t['rap_tip_text'] ?></p>
            <a class="lt-link small" href="index.php?page=rappels_print<?= $filter !== '' ? '&amp;owner=' . e($filter) : '' ?>"><i class="icofont-print mr-1"></i><?= $t['rap_print_title'] ?></a>
        </div>
    </div>
</div>
