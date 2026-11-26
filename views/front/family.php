<?php
/**
 * views/front/family.php
 * Espace famille, page d'accueil : mes familles, les invitations reçues (accepter en
 * choisissant ce que je partage, ou refuser) et la création d'une famille.
 * Variables : $families, $invitations, $myTag (mon code privé), $errors, $old, $flash
 */
$roleBadge = ['manager' => 'lt-fam-role-manager', 'caregiver' => 'lt-fam-role-caregiver', 'member' => 'lt-fam-role-member'];
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
                    <span class="text-white"><?= $t['fam_subtitle'] ?></span>
                    <h1 class="mb-5 text-lg"><?= $t['fam_title'] ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Invitations reçues : rien n'est partagé avant d'accepter, et c'est l'invité qui choisit quoi -->
        <?php foreach ($invitations as $inv): ?>
            <div class="lt-card lt-fam-invite mb-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                    <h4 class="mb-1 title-color"><i class="icofont-envelope-open mr-2"></i><?= sprintf($t['fam_invite_from'], '<strong>' . e($inv['invite_par']) . '</strong>', '<strong>' . e($inv['famille']) . '</strong>') ?></h4>
                    <span class="lt-fam-expire"><i class="icofont-clock-time mr-1"></i><?= sprintf($t['fam_expires_in'], intdiv((int) $inv['minutes_left'], 60), (int) $inv['minutes_left'] % 60) ?></span>
                </div>
                <p class="mb-3">
                    <?= $t['fam_relation'] ?> : <strong><?= $t['fam_rel_' . $inv['relation']] ?? '' ?></strong>
                    <?php if ($inv['membre_id'] !== null): ?><br><small class="text-muted"><?= $t['fam_invite_link_note'] ?></small><?php endif; ?>
                </p>
                <form method="POST" action="index.php?page=family">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="form" value="respond">
                    <input type="hidden" name="invitation_id" value="<?= (int) $inv['id'] ?>">
                    <p class="mb-2"><strong><?= $t['fam_share_choose'] ?></strong></p>
                    <div class="lt-fam-shares mb-3">
                        <?php foreach (FamilyController::SHARES as $type): ?>
                            <label class="lt-fam-check"><input type="checkbox" name="share[<?= $type ?>]" value="1"> <?= $t['fam_share_' . $type] ?></label>
                        <?php endforeach; ?>
                    </div>
                    <p class="lt-note mb-3"><i class="icofont-lock mr-1"></i><?= $t['fam_share_note'] ?></p>
                    <button type="submit" name="answer" value="accept" class="btn btn-main btn-round-full mr-2"><?= $t['fam_btn_accept'] ?></button>
                    <button type="submit" name="answer" value="refuse" class="btn btn-main-2 btn-round-full"><?= $t['fam_btn_refuse'] ?></button>
                </form>
            </div>
        <?php endforeach; ?>

        <div class="row">
            <!-- Mes familles -->
            <div class="col-lg-7 mb-4">
                <h2 class="title-color mb-3"><?= $t['fam_my_families'] ?></h2>
                <?php if (empty($families)): ?>
                    <div class="lt-module">
                        <i class="icofont-users-alt-4"></i>
                        <div>
                            <h5 class="mb-1"><?= $t['fam_none_title'] ?></h5>
                            <p class="mb-0"><?= $t['fam_none_text'] ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php foreach ($families as $f): ?>
                    <a class="lt-fam-row" href="index.php?page=family_view&amp;id=<?= (int) $f['id'] ?>">
                        <span class="lt-fam-row-icon"><i class="icofont-home"></i></span>
                        <span class="lt-fam-row-text">
                            <strong><?= e($f['nom']) ?></strong>
                            <small><?= sprintf($t['fam_members_count'], (int) $f['nb_membres']) ?></small>
                        </span>
                        <span class="lt-fam-role <?= $roleBadge[$f['role']] ?? '' ?>"><?= $t['fam_role_' . $f['role']] ?></span>
                        <i class="icofont-simple-right"></i>
                    </a>
                <?php endforeach; ?>

                <div class="lt-fam-howto mt-4">
                    <h5 class="title-color"><i class="icofont-question-circle mr-2"></i><?= $t['fam_how_title'] ?></h5>
                    <ol class="mb-0">
                        <li><?= $t['fam_how_1'] ?></li>
                        <li><?= $t['fam_how_2'] ?></li>
                        <li><?= $t['fam_how_3'] ?></li>
                    </ol>
                </div>
            </div>

            <!-- Créer une famille -->
            <div class="col-lg-5 mb-4">
                <div class="lt-card">
                    <h3 class="title-color mb-2"><i class="icofont-plus-circle mr-2"></i><?= $t['fam_create_title'] ?></h3>
                    <p class="mb-4"><?= $t['fam_create_text'] ?></p>
                    <form class="appoinment-form" method="POST" action="index.php?page=family" <?= validationAttrs($t) ?>>
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="form" value="create">
                        <div class="form-group">
                            <label for="nom"><?= $t['fam_name'] ?></label>
                            <input type="text" id="nom" name="nom" class="form-control" required data-rule="famille" maxlength="60"
                                   placeholder="<?= e($t['fam_name_placeholder']) ?>" value="<?= e($old['nom']) ?>">
                        </div>
                        <button type="submit" class="btn btn-main btn-round-full btn-block"><?= $t['fam_btn_create'] ?> <i class="icofont-simple-right ml-2"></i></button>
                    </form>
                </div>
                <div class="lt-code mt-4">
                    <span><?= $t['fam_your_tag'] ?></span>
                    <strong>#<?= e($myTag) ?></strong>
                </div>
                <p class="lt-code-note"><?= $t['fam_your_tag_note'] ?></p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
