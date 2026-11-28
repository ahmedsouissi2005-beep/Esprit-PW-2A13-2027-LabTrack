<?php
/**
 * views/front/rappel_form.php
 * Ajouter ou modifier un rappel de médicament (pour moi ou un profil à charge).
 * Variables : $reminder (RappelModel ou null), $owners, $old, $errors
 */
$editing = $reminder !== null;
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
                    <h1 class="mb-5 text-lg"><?= $editing ? $t['rap_edit_reminder'] : $t['rap_add_reminder'] ?></h1>
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
                    <form class="appoinment-form" method="POST" action="index.php?page=rappel_form<?= $editing ? '&amp;id=' . $reminder->getId() : '' ?>" <?= validationAttrs($t) ?>>
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
                        <div class="form-row">
                            <div class="form-group col-md-7">
                                <label for="medicament"><?= $t['rap_medicament'] ?></label>
                                <input type="text" id="medicament" name="medicament" class="form-control" required data-rule="label" maxlength="100"
                                       placeholder="<?= e($t['rap_medicament_ph']) ?>" value="<?= e($old['medicament']) ?>">
                            </div>
                            <div class="form-group col-md-5">
                                <label for="dose"><?= $t['rap_dose'] ?></label>
                                <input type="text" id="dose" name="dose" class="form-control" maxlength="50" placeholder="<?= e($t['rap_dose_ph']) ?>" value="<?= e($old['dose']) ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="frequence"><?= $t['rap_frequence'] ?></label>
                                <select id="frequence" name="frequence" class="form-control" required data-editing="<?= $editing ? '1' : '0' ?>">
                                    <?php for ($n = 1; $n <= RappelModel::MAX_FREQUENCE; $n++): ?>
                                        <option value="<?= $n ?>" <?= (int) $old['frequence'] === $n ? 'selected' : '' ?>><?= sprintf($t['rap_frequence_opt'], $n) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="date_debut"><?= $t['rap_date_debut'] ?></label>
                                <input type="date" id="date_debut" name="date_debut" class="form-control" required data-rule="date" value="<?= e($old['date_debut']) ?>">
                            </div>
                            <div class="form-group col-md-4">
                                <label for="date_fin"><?= $t['rap_date_fin'] ?> <small class="text-muted">(<?= $t['rap_optional'] ?>)</small></label>
                                <input type="date" id="date_fin" name="date_fin" class="form-control" data-rule="date" data-after="date_debut" value="<?= e($old['date_fin']) ?>">
                            </div>
                        </div>
                        <!-- Une heure par prise : la fréquence choisie affiche autant de champs (6 au maximum) -->
                        <div class="form-group">
                            <label><?= $t['rap_heures'] ?></label>
                            <div class="form-row" id="heures-list">
                                <?php for ($i = 0; $i < RappelModel::MAX_FREQUENCE; $i++):
                                    $active = $i < (int) $old['frequence']; ?>
                                    <div class="form-group col-md-4 lt-heure-slot" <?= $active ? '' : 'hidden' ?>>
                                        <label for="heure_<?= $i ?>" class="small text-muted mb-1"><?= sprintf($t['rap_prise_n'], $i + 1) ?></label>
                                        <input type="time" id="heure_<?= $i ?>" name="heures[]" class="form-control lt-heure-input" data-rule="time"
                                               value="<?= e($old['heures'][$i] ?? '') ?>" <?= $active ? 'required' : 'disabled' ?>>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="note"><?= $t['rap_note'] ?> <small class="text-muted">(<?= $t['rap_optional'] ?>)</small></label>
                            <input type="text" id="note" name="note" class="form-control" maxlength="200" placeholder="<?= e($t['rap_note_ph']) ?>" value="<?= e($old['note']) ?>">
                        </div>
                        <!-- Stock (facultatif) : chaque prise cochée "pris" consomme "unités par prise" -->
                        <div class="form-row">
                            <div class="form-group col-md-7">
                                <label for="stock"><?= $t['rap_stock'] ?> <small class="text-muted">(<?= $t['rap_optional'] ?>)</small></label>
                                <input type="number" id="stock" name="stock" class="form-control" min="0" max="<?= RappelController::STOCK_MAX ?>" data-rule="count"
                                       placeholder="<?= e($t['rap_stock_ph']) ?>" value="<?= e($old['stock']) ?>">
                            </div>
                            <div class="form-group col-md-5">
                                <label for="unites"><?= $t['rap_units'] ?></label>
                                <input type="number" id="unites" name="unites" class="form-control" min="1" max="<?= RappelController::UNITS_MAX ?>" required data-rule="count"
                                       value="<?= e($old['unites']) ?>">
                            </div>
                        </div>
                        <p class="lt-note"><i class="icofont-box mr-1"></i><?= e(sprintf($t['rap_stock_help'], RappelController::STOCK_ALERT_DAYS)) ?></p>
                        <p class="lt-note"><i class="icofont-info-circle mr-1"></i><?= $t['rap_form_help'] ?></p>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-3"><?= $editing ? $t['btn_update'] : $t['rap_add_reminder'] ?></button>
                    </form>
                    <p class="text-center mt-3 mb-0"><a class="lt-link" href="index.php?page=rappels&amp;tab=medicines"><?= $t['rap_back'] ?></a></p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
<script src="<?= e(asset('assets/js/rappel-form.js')) ?>"></script>

</body>
</html>
