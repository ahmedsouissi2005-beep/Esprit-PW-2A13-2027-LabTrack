<?php
/**
 * views/front/bilan_view.php
 * Détail d'un bilan : chaque valeur avec sa norme et son statut.
 * Variables : $bilan, $results, $owner, $showOwner, $flash
 */
$badge = ['normal' => 'success', 'bas' => 'warning', 'haut' => 'warning', 'inconnu' => 'secondary'];
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
                    <span class="text-white"><?= e($bilan['date_bilan']) ?><?= $showOwner ? ' · ' . e($owner['self'] ? $t['bil_me'] : $owner['label']) : '' ?></span>
                    <h1 class="mb-5 text-lg"><?= e($bilan['titre']) ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-5">
    <div class="container">
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
        <?php endif; ?>
        <div class="lt-card lt-card-compact">
            <?php if ((string) $bilan['laboratoire'] !== ''): ?><p class="mb-2"><strong><?= $t['bil_col_lab'] ?> :</strong> <?= e($bilan['laboratoire']) ?></p><?php endif; ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr><th><?= $t['bil_th_name'] ?></th><th><?= $t['bil_th_value'] ?></th><th><?= $t['bil_ref_range'] ?></th><th><?= $t['bil_col_flag'] ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($results as $r):
                        $min = BilanController::fmt($r['ref_min']);
                        $max = BilanController::fmt($r['ref_max']);
                        $range = $min !== '' && $max !== '' ? $min . ' – ' . $max : ($min !== '' ? '≥ ' . $min : ($max !== '' ? '≤ ' . $max : '—'));
                        $val = $r['valeur'] !== null ? BilanController::fmt($r['valeur']) : (string) $r['valeur_texte']; ?>
                        <tr>
                            <td><?= e($r['nom']) ?></td>
                            <td><strong><?= e($val) ?></strong> <?= e((string) $r['unite']) ?></td>
                            <td><?= e($range) ?></td>
                            <td><span class="badge badge-<?= $badge[$r['statut']] ?>"><?= $t['bil_status_' . $r['statut']] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="lt-note mt-3"><i class="icofont-info-circle mr-1"></i><?= $t['bil_disclaimer'] ?></p>
        <div class="d-flex justify-content-between flex-wrap">
            <a class="lt-link" href="index.php?page=bilans"><?= $t['bil_back'] ?></a>
            <?php if ($bilan['fichier']): ?><a class="lt-link" target="_blank" rel="noopener" href="index.php?page=bilan_file&amp;id=<?= (int) $bilan['id'] ?>"><?= $t['bil_view_original'] ?></a><?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
