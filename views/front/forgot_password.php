<?php
/**
 * views/front/forgot_password.php
 * Mot de passe oublié, étape 1 : saisir son email pour recevoir un code (valable 5 minutes).
 * Variables : $pageTitle, $currentPage = 'forgot_password', $errors, $oldEmail
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
                    <span class="text-white"><?= $t['forgot_subtitle'] ?></span>
                    <h1 class="text-capitalize mb-5 text-lg"><?= $t['forgot_title'] ?></h1>
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
                    <img src="assets/front/images/logo-labtrack.png" alt="LabTrack" class="lt-card-logo">
                    <h2 class="mb-2 title-color"><?= $t['forgot_title'] ?></h2>
                    <p class="mb-4"><?= $t['forgot_intro'] ?></p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?= e($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form class="appoinment-form" method="POST" action="index.php?page=forgot_password" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="form-group">
                            <label for="email"><?= $t['email'] ?></label>
                            <input type="email" id="email" name="email" class="form-control" required data-rule="email" autocomplete="email" value="<?= e($oldEmail) ?>">
                        </div>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-4"><?= $t['btn_send_code'] ?> <i class="icofont-paper-plane ml-2"></i></button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        <a href="index.php?page=login" class="lt-link"><?= $t['link_login'] ?></a>
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
