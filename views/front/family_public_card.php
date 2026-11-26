<?php
/**
 * views/front/family_public_card.php
 * Page publique ouverte par le QR code de la carte d'urgence (sans connexion).
 * Elle montre UNIQUEMENT les informations d'urgence, rien d'autre du compte.
 * Variables : $card (ligne fiche_urgence + nom, ou null si le lien est invalide), $age
 */
$val = fn(string $k): string => (string) ($card[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= $dir ?>">
<head>
    <?php require_once __DIR__ . '/includes/head.php'; ?>
    <meta name="robots" content="noindex, nofollow">
</head>
<body id="top" class="lt-public-card">
<?php require_once __DIR__ . '/includes/spinner.php'; ?>

<section class="section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <p class="text-center mb-4"><img src="assets/front/images/logo-horizontal.png" alt="LabTrack" class="lt-logo"></p>
                <?php if (!$card): ?>
                    <div class="lt-card text-center">
                        <h3 class="title-color"><?= $t['fam_public_invalid_title'] ?></h3>
                        <p class="mb-0"><?= $t['fam_public_invalid'] ?></p>
                    </div>
                <?php else: ?>
                    <div class="lt-urgence">
                        <div class="lt-urgence-head">
                            <i class="icofont-heart-beat"></i>
                            <div>
                                <small><?= $t['fam_card_kicker'] ?></small>
                                <h2><?= e($val('nom')) ?></h2>
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
                        <p class="lt-urgence-foot"><?= $t['fam_card_foot'] ?> · <?= e(substr($val('updated_at'), 0, 10)) ?></p>
                    </div>
                <?php endif; ?>
                <p class="lt-note text-center mt-3"><?= $t['footer'] ?></p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
