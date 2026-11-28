<?php
/**
 * views/front/bilans.php
 * Module "Analyses sanguines" : liste des bilans.
 * Variables : $bilans (lignes bilan_sanguin + nb_valeurs), $owners, $flash
 */
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
                    <span class="text-white"><?= $t['bil_subtitle'] ?></span>
                    <h1 class="mb-5 text-lg"><?= $t['mod_analyses'] ?></h1>
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
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
            <p class="lt-note mb-0"><i class="icofont-info-circle mr-1"></i><?= $t['bil_disclaimer'] ?></p>
            <a class="btn btn-main btn-round-full" href="index.php?page=bilan_upload"><i class="icofont-upload-alt mr-1"></i><?= $t['bil_add'] ?></a>
        </div>

        <?php if (empty($bilans)): ?>
            <div class="lt-card text-center"><p class="mb-0"><?= $t['bil_empty'] ?></p></div>
        <?php else: ?>
            <div class="lt-card lt-card-compact">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th><?= $t['bil_col_date'] ?></th>
                                <th><?= $t['bil_col_title'] ?></th>
                                <?php if (count($owners) > 1): ?><th><?= $t['bil_col_for'] ?></th><?php endif; ?>
                                <th><?= $t['bil_col_lab'] ?></th>
                                <th><?= $t['bil_col_values'] ?></th>
                                <th><?= $t['bil_col_flag'] ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bilans as $b):
                            $ownerKey = $b['user_id'] !== null ? 'u' . $b['user_id'] : 'm' . $b['membre_id']; ?>
                            <tr>
                                <td><?= e($b['date_bilan']) ?></td>
                                <td><a class="lt-link" href="index.php?page=bilan_view&amp;id=<?= (int) $b['id'] ?>"><?= e($b['titre']) ?></a></td>
                                <?php if (count($owners) > 1): ?><td><?= e($owners[$ownerKey]['self'] ? $t['bil_me'] : $owners[$ownerKey]['label']) ?></td><?php endif; ?>
                                <td><?= e((string) $b['laboratoire']) ?></td>
                                <td><?= (int) $b['nb_valeurs'] ?></td>
                                <td><?= $b['hors_norme'] ? '<span class="badge badge-warning">' . $t['bil_flag_out'] . '</span>' : '<span class="badge badge-success">' . $t['bil_flag_ok'] . '</span>' ?></td>
                                <td class="text-right">
                                    <form method="POST" action="index.php?page=bilan_delete" class="lt-inline-form" data-confirm="<?= e(sprintf($t['bil_confirm_delete'], $b['titre'])) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= $t['bil_delete'] ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
