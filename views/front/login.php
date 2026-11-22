<?php
/**
 * views/front/login.php
 * Variables : $pageTitle, $currentPage = 'login', $errors
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
                    <span class="text-white"><?= $t['login_subtitle'] ?></span>
                    <h1 class="text-capitalize mb-5 text-lg"><?= $t['login_title'] ?></h1>
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
                    <h2 class="mb-2 title-color"><?= $t['login_title'] ?></h2>
                    <p class="mb-4"><?= $t['login_subtitle'] ?></p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?= e($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($flash)): ?>
                        <div class="alert alert-warning"><?= e($t[$flash['message']] ?? '') ?></div>
                    <?php endif; ?>

                    <?php if (!empty($_GET['registered'])): ?>
                        <div class="alert alert-success"><?= $t['msg_registered'] ?></div>
                    <?php endif; ?>

                    <form class="appoinment-form" method="POST" action="index.php?page=login" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <div class="form-group">
                            <label for="email"><?= $t['email'] ?></label>
                            <input type="email" id="email" name="email" class="form-control" required data-rule="email" autocomplete="email">
                        </div>
                        <div class="form-group">
                            <label for="password"><?= $t['password'] ?></label>
                            <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-main btn-round-full btn-block mt-4"><?= $t['btn_login'] ?> <i class="icofont-simple-right ml-2"></i></button>
                    </form>

                    <p class="text-center mt-4 mb-0">
                        <?= $t['no_account'] ?> <a href="index.php?page=register" class="lt-link"><?= $t['link_register'] ?></a>
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
