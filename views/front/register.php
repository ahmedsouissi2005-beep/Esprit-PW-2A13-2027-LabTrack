<?php
/**
 * views/front/register.php
 * Variables : $pageTitle, $currentPage = 'register', $errors, $old
 */
$old = $old ?? [];
$selectedLang = $old['langue'] ?? $lang;
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
                    <span class="text-white"><?= $t['register_subtitle'] ?></span>
                    <h1 class="text-capitalize mb-5 text-lg"><?= $t['register_title'] ?></h1>
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
                    <img src="assets/front/images/logo-labtrack.png" alt="LabTrack" class="lt-card-logo">
                    <h2 class="mb-2 title-color"><?= $t['register_title'] ?></h2>
                    <p class="mb-4"><?= $t['register_subtitle'] ?></p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?= e($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form class="appoinment-form" method="POST" action="index.php?page=register" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="nom"><?= $t['nom'] ?></label>
                                    <input type="text" id="nom" name="nom" class="form-control" required data-rule="nom" maxlength="100" value="<?= e($old['nom'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="email"><?= $t['email'] ?></label>
                                    <input type="email" id="email" name="email" class="form-control" required data-rule="email" maxlength="150" value="<?= e($old['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label for="langue"><?= $t['langue_pref'] ?></label>
                                    <select id="langue" name="langue" class="form-control">
                                        <?php foreach (['fr', 'ar', 'en'] as $code): ?>
                                            <option value="<?= $code ?>" <?= $selectedLang === $code ? 'selected' : '' ?>><?= $t['lang_' . $code] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="password"><?= $t['password_hint'] ?></label>
                                    <input type="password" id="password" name="password" class="form-control" required data-rule="password" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="password_confirm"><?= $t['password_confirm'] ?></label>
                                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required data-match="password" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-3"><?= $t['btn_register'] ?> <i class="icofont-simple-right ml-2"></i></button>
                    </form>

                    <p class="lt-note mt-4"><i class="icofont-info-circle mr-1"></i><?= $t['register_note'] ?></p>
                    <p class="text-center mt-3 mb-0">
                        <?= $t['have_account'] ?> <a href="index.php?page=login" class="lt-link"><?= $t['link_login'] ?></a>
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
