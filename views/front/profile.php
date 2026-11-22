<?php
/**
 * views/front/profile.php
 * Modifier son nom, sa langue préférée et son mot de passe.
 * Variables : $pageTitle, $currentPage = 'profile', $user, $profileErrors, $passwordErrors, $flash
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
                    <span class="text-white"><?= e($user->getEmail()) ?> · <?= $t['your_code'] ?> #<?= e($user->getCode()) ?></span>
                    <h1 class="text-capitalize mb-5 text-lg"><?= $t['profile_title'] ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>">
                <?= e($t[$flash['message']] ?? '') ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulaire 1 : informations personnelles -->
            <div class="col-lg-6 mb-4">
                <div class="lt-card h-100">
                    <h3 class="title-color mb-4"><i class="icofont-ui-user mr-2"></i><?= $t['profile_info'] ?></h3>
                    <?php if (!empty($profileErrors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($profileErrors as $error): ?>
                                <div><?= e($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form class="appoinment-form" method="POST" action="index.php?page=profile" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="form" value="profile">
                        <div class="form-group">
                            <label for="nom"><?= $t['nom'] ?></label>
                            <input type="text" id="nom" name="nom" class="form-control" required data-rule="nom" maxlength="100" value="<?= e($user->getNom()) ?>">
                        </div>
                        <div class="form-group">
                            <label for="langue"><?= $t['langue_pref'] ?></label>
                            <select id="langue" name="langue" class="form-control">
                                <?php foreach (['fr', 'ar', 'en'] as $code): ?>
                                    <option value="<?= $code ?>" <?= $user->getLangue() === $code ? 'selected' : '' ?>><?= $t['lang_' . $code] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-main btn-round-full mt-3"><?= $t['btn_save'] ?></button>
                    </form>
                </div>
            </div>

            <!-- Formulaire 2 : mot de passe -->
            <div class="col-lg-6 mb-4">
                <div class="lt-card h-100">
                    <h3 class="title-color mb-4"><i class="icofont-lock mr-2"></i><?= $t['password_section'] ?></h3>
                    <?php if (!empty($passwordErrors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($passwordErrors as $error): ?>
                                <div><?= e($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <form class="appoinment-form" method="POST" action="index.php?page=profile" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="form" value="password">
                        <div class="form-group">
                            <label for="current_password"><?= $t['current_password'] ?></label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                        </div>
                        <div class="form-group">
                            <label for="new_password"><?= $t['new_password'] ?></label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required data-rule="password" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="new_password_confirm"><?= $t['new_password_confirm'] ?></label>
                            <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control" required data-match="new_password" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-main-2 btn-round-full mt-3"><?= $t['btn_change_password'] ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
