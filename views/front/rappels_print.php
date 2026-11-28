<?php
/**
 * views/front/rappels_print.php
 * Plan de prise imprimable : les médicaments en cours rangés par moment de la journée,
 * avec des cases à cocher pour la semaine, et les prochains rendez-vous.
 * Variables : $owner, $reminders (RappelModel[]), $upcoming (RendezVousModel[])
 */
$moments = ['morning' => ['00:00', '11:59'], 'noon' => ['12:00', '16:59'], 'evening' => ['17:00', '20:59'], 'night' => ['21:00', '23:59']];
$byMoment = [];
// Une ligne par prise : un médicament 3 fois par jour apparaît à 3 moments de la journée
foreach ($reminders as $r) {
    foreach ($r->getHeures() as $heure) {
        foreach ($moments as $key => [$from, $to]) {
            if ($heure >= $from && $heure <= $to) {
                $byMoment[$key][] = ['r' => $r, 'heure' => $heure];
                break;
            }
        }
    }
}
foreach ($byMoment as &$list) {
    usort($list, fn($a, $b) => strcmp($a['heure'], $b['heure']));
}
unset($list);
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $dir ?>">
<head>
    <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body id="top" class="lt-print-page">
<?php require_once __DIR__ . '/includes/spinner.php'; ?>
<div class="lt-no-print"><?php require_once __DIR__ . '/includes/navbar.php'; ?></div>

<section class="section">
    <div class="container">
        <div class="lt-no-print d-flex flex-wrap justify-content-between align-items-center mb-4">
            <a class="lt-link" href="index.php?page=rappels&amp;tab=medicines"><i class="icofont-simple-left mr-1"></i><?= $t['rap_back'] ?></a>
            <button type="button" class="btn btn-main btn-round-full" data-print><i class="icofont-print mr-1"></i><?= $t['fam_btn_print'] ?></button>
        </div>

        <div class="lt-plan">
            <div class="lt-plan-head">
                <img src="assets/front/images/logo-horizontal.png" alt="LabTrack" class="lt-logo">
                <div>
                    <h2 class="mb-0"><?= $t['rap_print_title'] ?></h2>
                    <strong><?= e($owner['label']) ?></strong> · <?= sprintf($t['rap_print_date'], date('d/m/Y')) ?>
                </div>
            </div>

            <?php if (!$reminders): ?><p class="text-muted"><?= $t['rap_no_reminders'] ?></p><?php endif; ?>
            <?php foreach ($moments as $key => $_): if (empty($byMoment[$key])) { continue; } ?>
                <h4 class="lt-plan-moment"><?= $t['rap_moment_' . $key] ?></h4>
                <table class="lt-plan-table">
                    <thead>
                    <tr>
                        <th><?= $t['rap_heure'] ?></th><th><?= $t['rap_medicament'] ?></th><th><?= $t['rap_dose'] ?></th><th><?= $t['rap_note'] ?></th>
                        <?php for ($d = 1; $d <= 7; $d++): ?><th class="lt-plan-day"><?= $t['fam_dow_' . $d] ?></th><?php endfor; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($byMoment[$key] as $line): $r = $line['r']; ?>
                        <tr>
                            <td><strong><?= e($line['heure']) ?></strong></td>
                            <td><?= e($r->getMedicament()) ?></td>
                            <td><?= e($r->getDose()) ?></td>
                            <td><?= e($r->getNote()) ?><?= $r->getDateFin() ? '<br><small>' . sprintf($t['rap_until'], e($r->getDateFin())) . '</small>' : '' ?></td>
                            <?php for ($d = 1; $d <= 7; $d++): ?><td class="lt-plan-day"><span class="lt-plan-box"></span></td><?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>

            <?php if ($upcoming): ?>
                <h4 class="lt-plan-moment"><?= $t['rap_upcoming'] ?></h4>
                <ul class="lt-plan-rdv">
                    <?php foreach ($upcoming as $rdv): ?>
                        <li><strong><?= e($rdv->getDate()) ?> <?= e($rdv->getTime()) ?></strong> · <?= e($rdv->getTitre()) ?><?= $rdv->getMedecin() !== '' ? ' · ' . e($rdv->getMedecin()) : '' ?><?= $rdv->getLieu() !== '' ? ' · ' . e($rdv->getLieu()) : '' ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="lt-plan-foot"><?= $t['footer'] ?></p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
