<?php
/**
 * views/front/family_member.php
 * Ajouter ou modifier un profil à charge (enfant, grand-parent sans compte LabTrack).
 * Variables : $family, $member (MembreModel ou null), $old, $errors
 */
$editing = $member !== null;
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
                    <span class="text-white"><?= e($family->getNom()) ?></span>
                    <h1 class="mb-5 text-lg"><?= $editing ? $t['fam_edit_dependent'] : $t['fam_add_dependent'] ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="lt-card">
                    <p class="mb-4"><?= $t['fam_dependent_intro'] ?></p>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form class="appoinment-form" method="POST"
                          action="index.php?page=family_member&amp;id=<?= $family->getId() ?><?= $editing ? '&amp;member=' . $member->getId() : '' ?>" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="form-group">
                            <label for="nom"><?= $t['nom'] ?></label>
                            <input type="text" id="nom" name="nom" class="form-control" required data-rule="nom" maxlength="100" value="<?= e($old['nom']) ?>">
                        </div>
                        <div class="form-group">
                            <label for="relation"><?= $t['fam_relation'] ?></label>
                            <select id="relation" name="relation" class="form-control" required>
                                <?php foreach (FamilyController::RELATIONS as $r): ?>
                                    <option value="<?= $r ?>" <?= $old['relation'] === $r ? 'selected' : '' ?>><?= $t['fam_rel_' . $r] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_naissance"><?= $t['fam_birth'] ?></label>
                            <input type="date" id="date_naissance" name="date_naissance" class="form-control" required data-rule="past-date"
                                   max="<?= date('Y-m-d') ?>" value="<?= e($old['date_naissance']) ?>">
                        </div>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-4"><?= $editing ? $t['btn_update'] : $t['fam_add_dependent'] ?></button>
                    </form>
                    <?php if ($editing): ?>
                        <p class="text-center mt-3 mb-0">
                            <a class="lt-link" href="index.php?page=family_card&amp;id=<?= $family->getId() ?>&amp;member=<?= $member->getId() ?>"><i class="icofont-heart-beat mr-1"></i><?= $t['fam_card_edit'] ?></a>
                        </p>
                    <?php endif; ?>
                    <p class="text-center mt-3 mb-0">
                        <a class="lt-link" href="index.php?page=family_view&amp;id=<?= $family->getId() ?>&amp;tab=members"><?= $t['fam_back'] ?></a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
