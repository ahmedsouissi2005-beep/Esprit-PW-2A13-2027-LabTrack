<?php
/**
 * views/front/rappels/appointments.php - onglet "Rendez-vous"
 * Recherche (titre, médecin, lieu), rendez-vous à venir (fait, annulé, modifier, supprimer)
 * et historique des rendez-vous passés, faits ou annulés.
 * Variables : $upcoming, $past, $query (+ celles de rappels.php)
 */
$statusClass = ['prevu' => 'lt-st-prevu', 'fait' => 'lt-st-fait', 'annule' => 'lt-st-annule'];
$ownerQuery = $filter !== '' ? '&amp;owner=' . e($filter) : '';
$card = function (RendezVousModel $rdv, bool $isPast) use ($t, $csrfToken, $ownerBadge, $statusClass): void { ?>
    <div class="lt-rdv <?= $isPast ? 'is-past' : '' ?>">
        <div class="lt-rdv-date">
            <strong><?= e(substr($rdv->getDate(), 8, 2)) ?></strong>
            <span><?= e(mb_substr($t['fam_month_' . (int) substr($rdv->getDate(), 5, 2)], 0, 4)) ?></span>
            <small><?= e(substr($rdv->getDate(), 0, 4)) ?></small>
        </div>
        <div class="lt-rdv-text">
            <h5 class="mb-1"><?= e($rdv->getTitre()) ?> <?= $ownerBadge($rdv->getOwnerKey()) ?></h5>
            <div class="lt-rdv-meta">
                <span><i class="icofont-clock-time"></i><?= e($rdv->getTime()) ?></span>
                <?php if ($rdv->getMedecin() !== ''): ?><span><i class="icofont-doctor"></i><?= e($rdv->getMedecin()) ?></span><?php endif; ?>
                <?php if ($rdv->getLieu() !== ''): ?><span><i class="icofont-location-pin"></i><?= e($rdv->getLieu()) ?></span><?php endif; ?>
            </div>
            <?php if ($rdv->getNote() !== ''): ?><small class="text-muted d-block mt-1"><?= e($rdv->getNote()) ?></small><?php endif; ?>
        </div>
        <div class="lt-rdv-side">
            <?php if ($isPast || $rdv->getStatut() !== 'prevu'): ?>
                <span class="lt-st <?= $statusClass[$rdv->getStatut()] ?>"><?= $t['rap_status_' . $rdv->getStatut()] ?></span>
            <?php else: $d = $rdv->daysLeft(); ?>
                <span class="lt-st lt-st-prevu"><?= $d === 0 ? $t['rap_today'] : ($d === 1 ? $t['rap_tomorrow'] : sprintf($t['rap_in_days'], $d)) ?></span>
            <?php endif; ?>
            <div class="lt-rdv-actions">
                <form method="POST" action="index.php?page=rdv_status" class="lt-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $rdv->getId() ?>">
                    <?php if ($rdv->getStatut() === 'prevu'): ?>
                        <button type="submit" name="statut" value="fait" class="btn btn-sm btn-outline-success" title="<?= e($t['rap_btn_done']) ?>"><i class="icofont-check"></i></button>
                        <button type="submit" name="statut" value="annule" class="btn btn-sm btn-outline-secondary" title="<?= e($t['rap_btn_cancel']) ?>"><i class="icofont-ban"></i></button>
                    <?php else: ?>
                        <button type="submit" name="statut" value="prevu" class="btn btn-sm btn-link lt-undo"><?= $t['rap_btn_reopen'] ?></button>
                    <?php endif; ?>
                </form>
                <a class="btn btn-sm btn-outline-primary" href="index.php?page=rdv_form&amp;id=<?= $rdv->getId() ?>" title="<?= e($t['btn_edit']) ?>"><i class="icofont-edit"></i></a>
                <form method="POST" action="index.php?page=rdv_delete" class="lt-inline-form" data-confirm="<?= e(sprintf($t['rap_confirm_delete_rdv'], $rdv->getTitre())) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $rdv->getId() ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e($t['btn_delete']) ?>"><i class="icofont-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
<?php };
?>
<div class="d-flex flex-wrap justify-content-between align-items-center lt-gap mb-3">
    <form class="d-flex lt-gap flex-grow-1 lt-search" method="get" action="index.php" role="search">
        <input type="hidden" name="page" value="rappels">
        <input type="hidden" name="tab" value="appointments">
        <?php if ($filter !== ''): ?><input type="hidden" name="owner" value="<?= e($filter) ?>"><?php endif; ?>
        <label for="q" class="sr-only"><?= $t['rap_search'] ?></label>
        <input id="q" type="search" name="q" class="form-control" value="<?= e($query) ?>" placeholder="<?= e($t['rap_search']) ?>" maxlength="100">
        <button type="submit" class="btn btn-outline-primary"><i class="icofont-search-1"></i></button>
        <?php if ($query !== ''): ?><a class="btn btn-link" href="index.php?page=rappels&amp;tab=appointments<?= $ownerQuery ?>"><?= $t['btn_reset'] ?></a><?php endif; ?>
    </form>
    <a class="btn btn-sm btn-main btn-round-full" href="index.php?page=rdv_form<?= $ownerQuery ?>"><i class="icofont-plus mr-1"></i><?= $t['rap_add_rdv'] ?></a>
</div>

<h4 class="title-color mb-3"><?= $t['rap_upcoming'] ?> <small class="text-muted">(<?= count($upcoming) ?>)</small></h4>
<?php if (!$upcoming): ?>
    <div class="lt-card lt-card-compact lt-empty mb-4"><i class="icofont-calendar"></i><p class="mb-0"><?= $query !== '' ? $t['no_results'] : $t['rap_no_upcoming'] ?></p></div>
<?php endif; ?>
<?php foreach ($upcoming as $rdv) { $card($rdv, false); } ?>

<?php if ($past): ?>
    <h4 class="title-color mt-5 mb-3"><?= $t['rap_past'] ?></h4>
    <?php foreach ($past as $rdv) { $card($rdv, true); } ?>
<?php endif; ?>
