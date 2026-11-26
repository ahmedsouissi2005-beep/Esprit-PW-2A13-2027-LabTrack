<?php
/**
 * views/front/rappels/medicines.php - onglet "Médicaments"
 * Tous les rappels (en cours puis terminés) : ajouter, modifier, supprimer, imprimer le plan.
 * Variables : $reminders (RappelModel[]) (+ celles de rappels.php)
 */
$today = date('Y-m-d');
$current = array_filter($reminders, fn(RappelModel $r) => $r->getDateFin() === null || $r->getDateFin() >= $today);
$finished = array_filter($reminders, fn(RappelModel $r) => $r->getDateFin() !== null && $r->getDateFin() < $today);
$ownerQuery = $filter !== '' ? '&amp;owner=' . e($filter) : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="title-color mb-2"><?= $t['rap_current'] ?> <small class="text-muted">(<?= count($current) ?>)</small></h4>
    <div class="d-flex lt-gap mb-2">
        <a class="btn btn-sm btn-outline-primary btn-round-full" href="index.php?page=rappels_print<?= $ownerQuery ?>"><i class="icofont-print mr-1"></i><?= $t['rap_print_title'] ?></a>
        <a class="btn btn-sm btn-main btn-round-full" href="index.php?page=rappel_form<?= $ownerQuery ?>"><i class="icofont-plus mr-1"></i><?= $t['rap_add_reminder'] ?></a>
    </div>
</div>

<?php if (!$current): ?>
    <div class="lt-card lt-card-compact lt-empty mb-4"><i class="icofont-pills"></i><p class="mb-0"><?= $t['rap_no_reminders'] ?></p></div>
<?php endif; ?>

<div class="row">
    <?php foreach ([$current, $finished] as $i => $list): ?>
        <?php if ($i === 1 && $finished): ?>
            <div class="col-12"><h5 class="title-color mt-3 mb-3"><?= $t['rap_finished'] ?></h5></div>
        <?php endif; ?>
        <?php foreach ($list as $r): /** @var RappelModel $r */ ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="lt-med <?= $i === 1 ? 'is-finished' : '' ?>">
                    <div class="lt-med-head">
                        <span class="lt-med-icon"><i class="icofont-pills"></i></span>
                        <div>
                            <h5 class="mb-0"><?= e($r->getMedicament()) ?></h5>
                            <small><?= $r->getDose() !== '' ? e($r->getDose()) : '&nbsp;' ?></small>
                        </div>
                        <span class="lt-med-time"><?= e($r->getHeure()) ?></span>
                    </div>
                    <ul class="list-unstyled lt-med-info">
                        <li><i class="icofont-calendar"></i><?= $r->getDateFin() === null
                            ? sprintf($t['rap_since'], e($r->getDateDebut()))
                            : sprintf($t['rap_from_to'], e($r->getDateDebut()), e($r->getDateFin())) ?></li>
                        <?php if ($r->getNote() !== ''): ?><li><i class="icofont-info-circle"></i><?= e($r->getNote()) ?></li><?php endif; ?>
                    </ul>
                    <div class="d-flex align-items-center lt-gap">
                        <?= $ownerBadge($r->getOwnerKey()) ?>
                        <a class="btn btn-sm btn-outline-primary ml-auto" href="index.php?page=rappel_form&amp;id=<?= $r->getId() ?>"><?= $t['btn_edit'] ?></a>
                        <form class="lt-inline-form" method="POST" action="index.php?page=rappel_delete" data-confirm="<?= e(sprintf($t['rap_confirm_delete_reminder'], $r->getMedicament())) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= $r->getId() ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e($t['btn_delete']) ?>"><i class="icofont-trash"></i><span class="sr-only"><?= $t['btn_delete'] ?></span></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
