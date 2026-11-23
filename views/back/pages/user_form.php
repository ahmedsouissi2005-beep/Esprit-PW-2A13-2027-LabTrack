<?php
/**
 * views/back/pages/user_form.php
 * CRUD du backoffice : ajout d'un compte (sans id) ou consultation / modification (avec id).
 * Variables : $heading, $editing, $target (UserModel|null), $isSelf, $old, $errors
 */
$action = 'index.php?page=admin_user_form' . ($editing ? '&id=' . $target->getId() : '');
?>
<div class="page-head">
    <div>
        <nav aria-label="Breadcrumb"><ol class="crumbs"><li><a href="index.php?page=admin"><?= $t['admin_dashboard'] ?></a></li><li><a href="index.php?page=admin_users"><?= $t['admin_users'] ?></a></li><li aria-current="page"><?= e($heading) ?></li></ol></nav>
        <h1 class="page-title"><?= e($heading) ?></h1>
        <p class="page-sub"><?= $editing ? $t['user_form_sub_edit'] : $t['user_form_sub_add'] ?></p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="<?= $editing ? 'col-xl-8' : 'col-xl-9' ?>">
        <section class="panel">
            <div class="panel-body">
                <!-- novalidate : la validation JavaScript (form-validation.js) affiche les erreurs
                     sous chaque champ ; le serveur revérifie TOUT dans AuthController. -->
                <form method="POST" action="<?= e($action) ?>" novalidate data-validate
                      data-msg-required="<?= e($t['err_field_required']) ?>"
                      data-msg-email="<?= e($t['err_email']) ?>"
                      data-msg-nom="<?= e($t['err_nom']) ?>"
                      data-msg-length="<?= e($t['err_password_length']) ?>"
                      data-msg-match="<?= e($t['err_password_match']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <fieldset class="form-section">
                        <legend><?= $t['section_identity'] ?></legend>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="nom"><?= $t['nom'] ?></label>
                                <input type="text" id="nom" name="nom" class="form-control" required data-rule="nom" maxlength="100" value="<?= e($old['nom']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email"><?= $t['email'] ?></label>
                                <input type="email" id="email" name="email" class="form-control" required data-rule="email" maxlength="150" value="<?= e($old['email']) ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="form-section">
                        <legend><?= $t['section_access'] ?></legend>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="role"><?= $t['role'] ?></label>
                                <select id="role" name="role" class="form-select" <?= $isSelf ? 'disabled' : '' ?>>
                                    <?php foreach (AuthController::ROLES as $r): ?>
                                        <option value="<?= $r ?>" <?= $old['role'] === $r ? 'selected' : '' ?>><?= $t['role_' . $r] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isSelf): ?>
                                    <div class="form-text"><?= $t['note_own_role'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="langue"><?= $t['langue_pref'] ?></label>
                                <select id="langue" name="langue" class="form-select">
                                    <?php foreach (AuthController::LANGUES as $code): ?>
                                        <option value="<?= $code ?>" <?= $old['langue'] === $code ? 'selected' : '' ?>><?= $t['lang_' . $code] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password"><?= $editing ? $t['password_optional'] : $t['password_hint'] ?></label>
                                <input type="password" id="password" name="password" class="form-control" autocomplete="new-password"
                                       data-rule="password" <?= $editing ? '' : 'required' ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirm"><?= $t['password_confirm'] ?></label>
                                <input type="password" id="password_confirm" name="password_confirm" class="form-control" autocomplete="new-password"
                                       data-match="password" <?= $editing ? '' : 'required' ?>>
                            </div>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <a class="btn btn-outline-secondary" href="index.php?page=admin_users"><?= $t['btn_cancel'] ?></a>
                        <button type="submit" class="btn btn-primary"><?= $editing ? $t['btn_save'] : $t['btn_create'] ?></button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <?php if ($editing): ?>
        <!-- Consultation : fiche du compte -->
        <div class="col-xl-4">
            <section class="panel" aria-labelledby="ficheTitle">
                <div class="panel-head"><h2 class="panel-title" id="ficheTitle"><?= $t['account_details'] ?></h2></div>
                <div class="panel-body">
                    <div class="person mb-3">
                        <?= adminAvatar($target->getId(), $target->getNom(), $target->getAvatar(), 'av-' . (($target->getId() % 5) + 1) . ' avatar-xl') ?>
                        <div>
                            <span class="person-name"><?= e($target->getNom()) ?></span>
                            <span class="person-meta"><?= e($target->getEmail()) ?></span>
                        </div>
                    </div>
                    <dl class="lt-details">
                        <dt><?= $t['col_id'] ?></dt><dd>#<?= $target->getId() ?></dd>
                        <dt><?= $t['role'] ?></dt><dd><?= $t['role_' . $target->getRole()] ?? e($target->getRole()) ?></dd>
                        <dt><?= $t['language'] ?></dt><dd><?= $t['lang_' . $target->getLangue()] ?? '' ?></dd>
                        <dt><?= $t['col_created'] ?></dt><dd><?= e($target->getCreatedAt()) ?></dd>
                    </dl>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>
