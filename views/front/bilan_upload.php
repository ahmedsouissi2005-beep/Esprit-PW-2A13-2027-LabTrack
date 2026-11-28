<?php
/**
 * views/front/bilan_upload.php
 * Envoi d'un rapport d'analyses (PDF ou photo).
 * Variables : $owners, $ownerKey, $errors
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
                    <span class="text-white"><?= $t['mod_analyses'] ?></span>
                    <h1 class="mb-5 text-lg"><?= $t['bil_upload_title'] ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="lt-card">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="index.php?page=bilan_upload" enctype="multipart/form-data" data-bilan-upload>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <?php if (count($owners) > 1): ?>
                            <div class="form-group">
                                <label for="owner"><?= $t['bil_for'] ?></label>
                                <select id="owner" name="owner" class="form-control" required>
                                    <?php foreach ($owners as $key => $o): ?>
                                        <option value="<?= e($key) ?>" <?= $ownerKey === $key ? 'selected' : '' ?>><?= e($o['self'] ? $t['bil_me'] : $o['label'] . ' (' . $o['family'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="owner" value="<?= e(array_key_first($owners)) ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="rapport"><?= $t['bil_file'] ?></label>
                            <input type="file" id="rapport" name="rapport" class="form-control-file" accept="application/pdf,image/jpeg,image/png,image/webp" required>
                            <small class="text-muted"><?= $t['bil_file_hint'] ?></small>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="consent" name="consent" value="1" required>
                            <label class="form-check-label" for="consent"><?= $t['bil_consent'] ?></label>
                        </div>
                        <p class="lt-note"><i class="icofont-info-circle mr-1"></i><?= $t['bil_review_note'] ?></p>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-3" data-busy="<?= e($t['bil_reading']) ?>"><?= $t['bil_upload_btn'] ?></button>
                    </form>
                    <p class="text-center mt-3 mb-0"><a class="lt-link" href="index.php?page=bilans"><?= $t['bil_back'] ?></a></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
<script src="<?= e(asset('assets/front/js/bilans.js')) ?>"></script>
</body>
</html>
