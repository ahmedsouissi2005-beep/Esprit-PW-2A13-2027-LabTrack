<?php
/**
 * views/front/bilan_review.php
 * Relecture des valeurs lues par l'IA : le patient corrige, puis enregistre.
 * Variables : $owner, $old (titre, date_bilan, laboratoire, resultats[]), $errors
 */
$rows = $old['resultats'] ?: [['nom' => '', 'valeur' => '', 'valeur_texte' => '', 'unite' => '', 'ref_min' => '', 'ref_max' => '']];
$cols = ['nom' => 'bil_th_name', 'valeur' => 'bil_th_value', 'valeur_texte' => 'bil_th_text', 'unite' => 'bil_th_unit', 'ref_min' => 'bil_th_min', 'ref_max' => 'bil_th_max'];
$rowHtml = function (int|string $i, array $r) use ($cols, $t): string {
    $h = '<tr data-row>';
    foreach ($cols as $k => $label) {
        $mode = in_array($k, ['valeur', 'ref_min', 'ref_max'], true) ? ' inputmode="decimal"' : '';
        $max = $k === 'nom' ? 100 : ($k === 'unite' ? 30 : ($k === 'valeur_texte' ? 50 : 14));
        $h .= '<td><input type="text" class="form-control form-control-sm" name="r[' . $i . '][' . $k . ']" maxlength="' . $max . '"' . $mode
            . ' aria-label="' . e($t[$label]) . '" value="' . e($r[$k] ?? '') . '"></td>';
    }
    return $h . '<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-row aria-label="' . e($t['bil_remove']) . '">&times;</button></td></tr>';
};
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
                    <span class="text-white"><?= e($owner['self'] ? $t['bil_me'] : $owner['label']) ?></span>
                    <h1 class="mb-5 text-lg"><?= $t['bil_review_title'] ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="lt-card">
            <div class="alert alert-info"><i class="icofont-eye-alt mr-1"></i><?= $t['bil_review_help'] ?></div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <form method="POST" action="index.php?page=bilan_review">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label for="titre"><?= $t['bil_f_titre'] ?></label>
                        <input type="text" id="titre" name="titre" class="form-control" required maxlength="150" value="<?= e($old['titre']) ?>">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="date_bilan"><?= $t['bil_f_date'] ?></label>
                        <input type="date" id="date_bilan" name="date_bilan" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= e($old['date_bilan']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="laboratoire"><?= $t['bil_f_lab'] ?> <small class="text-muted">(<?= $t['bil_optional'] ?>)</small></label>
                        <input type="text" id="laboratoire" name="laboratoire" class="form-control" maxlength="150" value="<?= e($old['laboratoire']) ?>">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm" id="bilan-rows">
                        <thead>
                            <tr>
                                <?php foreach ($cols as $label): ?><th><?= $t[$label] ?></th><?php endforeach; ?>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_values($rows) as $i => $r): ?><?= $rowHtml($i, $r) ?><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <template id="bilan-row-template"><?= $rowHtml('__I__', []) ?></template>
                <button type="button" class="btn btn-sm btn-outline-primary" id="bilan-add-row"><i class="icofont-plus mr-1"></i><?= $t['bil_add_row'] ?></button>

                <p class="lt-note mt-3"><i class="icofont-info-circle mr-1"></i><?= $t['bil_ref_note'] ?></p>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <a class="lt-link" href="index.php?page=bilan_upload"><?= $t['bil_cancel'] ?></a>
                    <button type="submit" class="btn btn-main btn-round-full"><?= $t['bil_save'] ?></button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
<script src="<?= e(asset('assets/front/js/bilans.js')) ?>"></script>
</body>
</html>
