<?php
/**
 * views/front/rdv_form.php
 * Ajouter ou modifier un rendez-vous médical (pour moi ou un profil à charge).
 * Variables : $rdv (RendezVousModel ou null), $owners, $old, $errors
 */
$editing = $rdv !== null;
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
                    <span class="text-white"><?= $t['mod_rappels'] ?></span>
                    <h1 class="mb-5 text-lg"><?= $editing ? $t['rap_edit_rdv'] : $t['rap_add_rdv'] ?></h1>
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
                    <form class="appoinment-form" method="POST" action="index.php?page=rdv_form<?= $editing ? '&amp;id=' . $rdv->getId() : '' ?>" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <?php if (count($owners) > 1): ?>
                            <div class="form-group">
                                <label for="owner"><?= $t['rap_for'] ?></label>
                                <select id="owner" name="owner" class="form-control" required>
                                    <?php foreach ($owners as $key => $o): ?>
                                        <option value="<?= e($key) ?>" <?= $old['owner'] === $key ? 'selected' : '' ?>><?= e($o['self'] ? $t['rap_me'] : $o['label'] . ' (' . $o['family'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="owner" value="<?= e(array_key_first($owners)) ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="titre"><?= $t['rap_titre'] ?></label>
                            <input type="text" id="titre" name="titre" class="form-control" required data-rule="label" maxlength="150"
                                   placeholder="<?= e($t['rap_titre_ph']) ?>" value="<?= e($old['titre']) ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="date"><?= $t['rap_date'] ?></label>
                                <input type="date" id="date" name="date" class="form-control" required data-rule="date" value="<?= e($old['date']) ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="heure"><?= $t['rap_heure'] ?></label>
                                <input type="time" id="heure" name="heure" class="form-control" required data-rule="time" value="<?= e($old['heure']) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="medecin"><?= $t['rap_medecin'] ?> <small class="text-muted">(<?= $t['rap_optional'] ?>)</small></label>
                                <input type="text" id="medecin" name="medecin" class="form-control" data-rule="label" maxlength="100" placeholder="<?= e($t['rap_medecin_ph']) ?>" value="<?= e($old['medecin']) ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="lieu"><?= $t['rap_lieu'] ?> <small class="text-muted">(<?= $t['rap_optional'] ?>)</small></label>
                                <input type="text" id="lieu" name="lieu" class="form-control" data-rule="label" maxlength="150" placeholder="<?= e($t['rap_lieu_ph']) ?>" value="<?= e($old['lieu']) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="note"><?= $t['rap_note'] ?> <small class="text-muted">(<?= $t['rap_optional'] ?>)</small></label>
                            <textarea id="note" name="note" class="form-control lt-textarea" rows="2" maxlength="500" placeholder="<?= e($t['rap_rdv_note_ph']) ?>"><?= e($old['note']) ?></textarea>
                        </div>
                        <?php if ($editing): ?>
                            <div class="form-group">
                                <label for="statut"><?= $t['rap_statut'] ?></label>
                                <select id="statut" name="statut" class="form-control">
                                    <?php foreach (RendezVousModel::STATUTS as $s): ?>
                                        <option value="<?= $s ?>" <?= $old['statut'] === $s ? 'selected' : '' ?>><?= $t['rap_status_' . $s] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="statut" value="prevu">
                        <?php endif; ?>
                        <p class="lt-note"><i class="icofont-envelope mr-1"></i><?= $t['rap_rdv_help'] ?></p>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-3"><?= $editing ? $t['btn_update'] : $t['rap_add_rdv'] ?></button>
                    </form>
                    <p class="text-center mt-3 mb-0"><a class="lt-link" href="index.php?page=rappels&amp;tab=appointments"><?= $t['rap_back'] ?></a></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
