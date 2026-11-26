<?php
/**
 * views/front/family/overview.php - onglet "Aperçu"
 * Alertes douces, une carte par membre (prochain rendez-vous, médicaments du jour,
 * ce qu'il partage, carte d'urgence) et campagnes de santé utiles à la famille.
 * Variables : $alerts, $summary, $campaigns (+ celles de family_view.php)
 */
?>
<div class="row">
    <div class="col-lg-8">
        <h3 class="title-color mb-3"><?= $t['fam_members_title'] ?></h3>
        <div class="row">
            <?php foreach ($members as $m): /** @var MembreModel $m */
                $s = $summary[$m->getId()] ?? [];
                $age = $m->getAge();
                $isMe = $m->getId() === $me->getId();
                $cardVisible = $isMe || $m->shares('dossier') || ($m->isDependent() && $isCaring); ?>
                <div class="col-md-6 mb-4">
                    <div class="lt-fam-member" style="border-color: <?= e($colors[$m->getId()]) ?>">
                        <div class="lt-fam-member-head">
                            <?= memberAvatar($m, $colors[$m->getId()]) ?>
                            <div>
                                <h5 class="mb-0"><?= e($m->getNom()) ?><?php if ($isMe): ?> <small class="text-muted"><?= $t['you'] ?></small><?php endif; ?></h5>
                                <small>
                                    <?= $m->isDependent() ? $t['fam_rel_' . $m->getRelation()] : $t['fam_role_' . $m->getRole()] ?>
                                    <?php if ($age !== null): ?> · <?= sprintf($t['fam_age'], $age) ?><?php endif; ?>
                                    <?php if ($m->isDependent()): ?> · <span class="lt-fam-dep"><?= $t['fam_dependent'] ?></span><?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <ul class="list-unstyled lt-fam-member-info">
                            <li><i class="icofont-calendar"></i>
                                <?php if (!$m->shares('rdv') && !$isMe): ?><span class="text-muted"><?= $t['fam_not_shared'] ?></span>
                                <?php elseif (!empty($s['next_rdv'])): ?><?= e($s['next_rdv']['titre']) ?> · <strong><?= e(substr($s['next_rdv']['date_rdv'], 0, 16)) ?></strong>
                                <?php else: ?><span class="text-muted"><?= $t['fam_no_rdv'] ?></span><?php endif; ?>
                            </li>
                            <li><i class="icofont-pills"></i>
                                <?php if (!$m->shares('rappels') && !$isMe): ?><span class="text-muted"><?= $t['fam_not_shared'] ?></span>
                                <?php elseif (($s['doses_today'] ?? null) !== null): ?><?= sprintf($t['fam_doses_today'], (int) $s['doses_taken'], (int) $s['doses_today']) ?>
                                <?php else: ?><span class="text-muted"><?= $t['fam_no_doses'] ?></span><?php endif; ?>
                            </li>
                        </ul>
                        <?php if (!$m->isDependent()): ?>
                            <div class="lt-fam-share-icons" title="<?= e($t['fam_shares_title']) ?>">
                                <?php foreach (FamilyController::SHARES as $type): ?>
                                    <span class="<?= $m->shares($type) ? 'on' : 'off' ?>"><?= $t['fam_share_' . $type] ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($cardVisible): ?>
                            <a class="btn btn-sm btn-outline-danger mt-3" href="index.php?page=family_card&amp;id=<?= $family->getId() ?>&amp;member=<?= $m->getId() ?>">
                                <i class="icofont-heart-beat mr-1"></i><?= $t['fam_card_title'] ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="lt-card lt-card-compact mb-4">
            <h4 class="title-color mb-3"><i class="icofont-notification mr-2"></i><?= $t['fam_alerts_title'] ?></h4>
            <?php if (empty($alerts)): ?>
                <p class="mb-0 text-muted"><?= $t['fam_no_alerts'] ?></p>
            <?php endif; ?>
            <?php foreach ($alerts as $a): ?>
                <div class="lt-fam-alert lt-fam-alert-<?= e($a['level']) ?>"><i class="<?= e($a['icon']) ?>"></i><span><?= e($a['text']) ?></span></div>
            <?php endforeach; ?>
            <p class="lt-note mt-3 mb-0"><?= $t['fam_alerts_note'] ?></p>
        </div>

        <?php if (!empty($campaigns)): ?>
            <div class="lt-card lt-card-compact mb-4">
                <h4 class="title-color mb-3"><i class="icofont-megaphone mr-2"></i><?= $t['fam_campaigns_title'] ?></h4>
                <?php foreach ($campaigns as $c): ?>
                    <div class="lt-fam-alert lt-fam-alert-info"><i class="icofont-info-circle"></i>
                        <span><?= e($c['titre']) ?><br><small><?= sprintf($t['fam_campaign_until'], e($c['date_fin'])) ?></small></span></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (in_array(false, $modules, true)): ?>
            <div class="lt-fam-howto">
                <h6 class="title-color"><i class="icofont-plugin mr-1"></i><?= $t['fam_modules_title'] ?></h6>
                <ul class="mb-0 small">
                    <?php foreach ($modules as $key => $ready): ?>
                        <li><?= $t['fam_module_' . $key] ?> : <strong class="<?= $ready ? 'text-success' : 'text-muted' ?>"><?= $ready ? $t['fam_module_ready'] : $t['fam_module_soon'] ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
