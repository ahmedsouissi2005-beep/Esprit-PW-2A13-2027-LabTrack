<?php
/**
 * views/front/reset_password.php
 * Mot de passe oublié, étape 2 : code reçu par email + nouveau mot de passe.
 * Le compte à rebours (labtrack.js) montre le temps restant avant l'expiration du code.
 * Variables : $pageTitle, $currentPage = 'reset_password', $errors, $flash, $resetEmail, $secondsLeft
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
                    <span class="text-white"><?= $t['reset_subtitle'] ?></span>
                    <h1 class="text-capitalize mb-5 text-lg"><?= $t['reset_title'] ?></h1>
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
                    <h2 class="mb-2 title-color"><?= $t['reset_title'] ?></h2>
                    <p class="mb-3"><?= sprintf($t['reset_intro'], '<strong>' . e($resetEmail) . '</strong>') ?></p>

                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?= e($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="lt-countdown<?= $secondsLeft === 0 ? ' is-over' : '' ?>" data-countdown="<?= (int) $secondsLeft ?>">
                        <span class="lt-countdown-running"><i class="icofont-clock-time mr-1"></i><?= $t['reset_time_left'] ?>
                            <strong class="lt-countdown-value"><?= sprintf('%d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60) ?></strong></span>
                        <span class="lt-countdown-over"><?= $t['reset_time_over'] ?></span>
                    </div>

                    <form class="appoinment-form" method="POST" action="index.php?page=reset_password" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="form-group">
                            <label for="code"><?= $t['reset_code'] ?></label>
                            <input type="text" id="code" name="code" class="form-control lt-code-input" required
                                   inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000">
                        </div>
                        <div class="form-group">
                            <label for="new_password"><?= $t['new_password'] ?></label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required data-rule="password" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="new_password_confirm"><?= $t['new_password_confirm'] ?></label>
                            <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control" required data-match="new_password" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-4"><?= $t['btn_reset_password'] ?> <i class="icofont-simple-right ml-2"></i></button>
                    </form>

                    <form method="POST" action="index.php?page=forgot_password" class="text-center mt-4">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="email" value="<?= e($resetEmail) ?>">
                        <button type="submit" class="btn btn-link lt-link p-0"><?= $t['btn_resend_code'] ?></button>
                        <span class="mx-2">·</span>
                        <a href="index.php?page=forgot_password" class="lt-link"><?= $t['link_other_email'] ?></a>
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
