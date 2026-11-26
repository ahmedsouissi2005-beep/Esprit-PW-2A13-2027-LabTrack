<?php
/**
 * views/front/family_card.php
 * Carte d'urgence d'un membre : à imprimer / enregistrer en PDF (bouton Imprimer),
 * avec un QR code qui ouvre la carte en ligne (à montrer à l'hôpital).
 * Variables : $family, $member, $card (ligne fiche_urgence ou null), $canEdit,
 *             $publicUrl, $qrSvg, $errors, $flash
 */
$val = fn(string $k): string => (string) ($card[$k] ?? '');
$age = $member->getAge();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $dir ?>">
<head>
    <?php require_once __DIR__ . '/includes/head.php'; ?>
</head>
<body id="top">
<?php require_once __DIR__ . '/includes/spinner.php'; ?>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<section class="page-title bg-1 lt-no-print">
    <div class="overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="block text-center">
                    <span class="text-white"><?= e($family->getNom()) ?></span>
                    <h1 class="mb-5 text-lg"><?= $t['fam_card_title'] ?></h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <p class="mb-3 lt-no-print"><a class="lt-link" href="index.php?page=family_view&amp;id=<?= $family->getId() ?>"><i class="icofont-simple-left mr-1"></i><?= $t['fam_back'] ?></a></p>
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> lt-no-print"><?= e($t[$flash['message']] ?? '') ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger lt-no-print"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- La carte (seule partie imprimée) -->
            <div class="col-lg-7 mb-4">
                <div class="lt-urgence">
                    <div class="lt-urgence-head">
                        <i class="icofont-heart-beat"></i>
                        <div>
                            <small><?= $t['fam_card_kicker'] ?></small>
                            <h2><?= e($member->getNom()) ?></h2>
                            <?php if ($age !== null): ?><span><?= sprintf($t['fam_age'], $age) ?></span><?php endif; ?>
                        </div>
                        <div class="lt-urgence-blood" title="<?= e($t['fam_card_blood']) ?>"><?= $val('groupe_sanguin') !== '' ? e($val('groupe_sanguin')) : '?' ?></div>
                    </div>
                    <dl class="lt-urgence-body">
                        <dt><?= $t['fam_card_allergies'] ?></dt><dd><?= $val('allergies') !== '' ? nl2br(e($val('allergies'))) : '—' ?></dd>
                        <dt><?= $t['fam_card_diseases'] ?></dt><dd><?= $val('maladies') !== '' ? nl2br(e($val('maladies'))) : '—' ?></dd>
                        <dt><?= $t['fam_card_treatments'] ?></dt><dd><?= $val('traitements') !== '' ? nl2br(e($val('traitements'))) : '—' ?></dd>
                        <dt><?= $t['fam_card_contact'] ?></dt><dd><?= $val('contact_urgence') !== '' ? e($val('contact_urgence')) : '—' ?></dd>
                    </dl>
                    <?php if ($qrSvg): ?>
                        <div class="lt-urgence-qr">
                            <?= $qrSvg /* SVG généré par QrCode::svg(), le texte encodé est échappé */ ?>
                            <p><?= $t['fam_qr_scan'] ?></p>
                        </div>
                    <?php endif; ?>
                    <p class="lt-urgence-foot"><?= $t['fam_card_foot'] ?><?= $card && !empty($card['updated_at']) ? ' · ' . e(substr($card['updated_at'], 0, 10)) : '' ?></p>
                </div>
                <div class="lt-no-print mt-3">
                    <button type="button" class="btn btn-main btn-round-full" data-print><i class="icofont-print mr-1"></i><?= $t['fam_btn_print'] ?></button>
                </div>
            </div>

            <div class="col-lg-5 lt-no-print">
                <?php if ($canEdit): ?>
                    <div class="lt-card lt-card-compact mb-4">
                        <h4 class="title-color mb-3"><i class="icofont-edit mr-2"></i><?= $t['fam_card_edit'] ?></h4>
                        <form class="appoinment-form" method="POST" action="index.php?page=family_card" <?= validationAttrs($t) ?>>
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="family_id" value="<?= $family->getId() ?>">
                            <input type="hidden" name="member_id" value="<?= $member->getId() ?>">
                            <input type="hidden" name="form" value="save">
                            <div class="form-group">
                                <label for="groupe_sanguin"><?= $t['fam_card_blood'] ?></label>
                                <select id="groupe_sanguin" name="groupe_sanguin" class="form-control">
                                    <option value=""><?= $t['fam_card_unknown'] ?></option>
                                    <?php foreach (FamilyController::BLOOD_GROUPS as $g): ?>
                                        <option value="<?= $g ?>" <?= $val('groupe_sanguin') === $g ? 'selected' : '' ?>><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php foreach (['allergies' => 'fam_card_allergies', 'maladies' => 'fam_card_diseases', 'traitements' => 'fam_card_treatments'] as $field => $label): ?>
                                <div class="form-group">
                                    <label for="<?= $field ?>"><?= $t[$label] ?></label>
                                    <textarea id="<?= $field ?>" name="<?= $field ?>" class="form-control lt-textarea" rows="2" maxlength="500"><?= e($val($field)) ?></textarea>
                                </div>
                            <?php endforeach; ?>
                            <div class="form-group">
                                <label for="contact_urgence"><?= $t['fam_card_contact'] ?></label>
                                <input type="text" id="contact_urgence" name="contact_urgence" class="form-control" maxlength="150"
                                       placeholder="<?= e($t['fam_card_contact_ph']) ?>" value="<?= e($val('contact_urgence')) ?>">
                            </div>
                            <button type="submit" class="btn btn-main btn-round-full btn-block"><?= $t['btn_update'] ?></button>
                        </form>
                    </div>

                    <div class="lt-card lt-card-compact">
                        <h4 class="title-color mb-2"><i class="icofont-qr-code mr-2"></i><?= $t['fam_qr_title'] ?></h4>
                        <p class="small mb-3"><?= $t['fam_qr_text'] ?></p>
                        <?php if ($publicUrl): ?>
                            <p class="small text-break mb-3"><a class="lt-link" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicUrl) ?></a></p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap lt-gap">
                            <form method="POST" action="index.php?page=family_card" <?= $publicUrl ? 'data-confirm="' . e($t['fam_confirm_qr_new']) . '"' : '' ?>>
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="family_id" value="<?= $family->getId() ?>">
                                <input type="hidden" name="member_id" value="<?= $member->getId() ?>">
                                <input type="hidden" name="form" value="qr_new">
                                <button type="submit" class="btn btn-sm btn-main btn-round-full"><?= $publicUrl ? $t['fam_btn_qr_new'] : $t['fam_btn_qr_create'] ?></button>
                            </form>
                            <?php if ($publicUrl): ?>
                                <form method="POST" action="index.php?page=family_card">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="family_id" value="<?= $family->getId() ?>">
                                    <input type="hidden" name="member_id" value="<?= $member->getId() ?>">
                                    <input type="hidden" name="form" value="qr_off">
                                    <button type="submit" class="btn btn-sm btn-outline-danger btn-round-full"><?= $t['fam_btn_qr_off'] ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <p class="lt-note mt-3 mb-0"><?= $t['fam_qr_local_note'] ?></p>
                    </div>
                <?php else: ?>
                    <div class="lt-fam-howto"><p class="mb-0"><i class="icofont-lock mr-1"></i><?= $t['fam_card_readonly'] ?></p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="lt-no-print"><?php require_once __DIR__ . '/includes/footer.php'; ?></div>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
