<?php
/**
 * views/front/family/members.php - onglet "Membres"
 * Liste des membres et rôles, invitation par nom + code privé (gestionnaire),
 * profils à charge, invitations en attente, mes partages (consentement),
 * quitter la famille, renommer / supprimer la famille (gestionnaire).
 * Variables : $pending, $tagLock (+ celles de family_view.php)
 */
$dependents = array_filter($members, fn(MembreModel $m) => $m->isDependent());
$fid = $family->getId();
?>
<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="lt-card lt-card-compact">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h4 class="title-color mb-2"><?= $t['fam_members_title'] ?> <small class="text-muted">(<?= count($members) ?>)</small></h4>
                <?php if ($isManager): ?>
                    <a class="btn btn-sm btn-main btn-round-full" href="index.php?page=family_member&amp;id=<?= $fid ?>"><i class="icofont-plus mr-1"></i><?= $t['fam_add_dependent'] ?></a>
                <?php endif; ?>
            </div>

            <?php foreach ($members as $m): /** @var MembreModel $m */
                $isMe = $m->getId() === $me->getId(); ?>
                <div class="lt-fam-line">
                    <?= memberAvatar($m, $colors[$m->getId()], 'lt-fam-avatar-sm') ?>
                    <div class="lt-fam-line-text">
                        <strong><?= e($m->getNom()) ?></strong><?php if ($isMe): ?> <small class="text-muted"><?= $t['you'] ?></small><?php endif; ?>
                        <small class="d-block">
                            <?= $t['fam_rel_' . $m->getRelation()] ?? '' ?>
                            <?php if ($m->isDependent()): ?> · <span class="lt-fam-dep"><?= $t['fam_dependent'] ?></span><?php endif; ?>
                        </small>
                    </div>
                    <div class="lt-fam-line-actions">
                        <?php if ($m->isDependent()): ?>
                            <?php if ($isCaring): ?>
                                <a class="btn btn-sm btn-outline-primary" href="index.php?page=family_member&amp;id=<?= $fid ?>&amp;member=<?= $m->getId() ?>"><?= $t['btn_edit'] ?></a>
                            <?php endif; ?>
                        <?php elseif ($isManager && !$isMe): ?>
                            <!-- Rôle : donner "gestionnaire" transmet le rôle (je deviens aidant) -->
                            <form class="lt-inline-form" method="POST" action="index.php?page=family_role"
                                  data-confirm-value="manager" data-confirm="<?= e(sprintf($t['fam_confirm_transfer'], $m->getNom())) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="family_id" value="<?= $fid ?>">
                                <input type="hidden" name="member_id" value="<?= $m->getId() ?>">
                                <label class="sr-only" for="role<?= $m->getId() ?>"><?= $t['role'] ?></label>
                                <select id="role<?= $m->getId() ?>" name="role" class="form-control form-control-sm lt-fam-select" data-autosubmit-select>
                                    <?php foreach (FamilyController::ROLES as $r): ?>
                                        <option value="<?= $r ?>" <?= $m->getRole() === $r ? 'selected' : '' ?>><?= $t['fam_role_' . $r] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <noscript><button type="submit" class="btn btn-sm btn-primary"><?= $t['btn_update'] ?></button></noscript>
                            </form>
                        <?php else: ?>
                            <span class="lt-fam-role lt-fam-role-<?= e($m->getRole()) ?>"><?= $t['fam_role_' . $m->getRole()] ?></span>
                        <?php endif; ?>
                        <?php if ($isManager && !$isMe): ?>
                            <form class="lt-inline-form" method="POST" action="index.php?page=family_member_remove"
                                  data-confirm="<?= e(sprintf($t['fam_confirm_remove'], $m->getNom())) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="family_id" value="<?= $fid ?>">
                                <input type="hidden" name="member_id" value="<?= $m->getId() ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e($t['fam_remove']) ?>"><i class="icofont-close"></i><span class="sr-only"><?= $t['fam_remove'] ?></span></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <p class="lt-note mt-3 mb-0"><?= $t['fam_roles_note'] ?></p>
        </div>

        <?php if ($isManager && !empty($pending)): ?>
            <div class="lt-card lt-card-compact mt-4">
                <h5 class="title-color mb-3"><i class="icofont-paper-plane mr-2"></i><?= $t['fam_pending_title'] ?></h5>
                <?php foreach ($pending as $p): ?>
                    <div class="lt-fam-line">
                        <div class="lt-fam-line-text">
                            <strong><?= e($p['nom']) ?></strong>
                            <small class="d-block"><?= $t['fam_rel_' . $p['relation']] ?? '' ?> ·
                                <?= sprintf($t['fam_expires_in'], intdiv((int) $p['minutes_left'], 60), (int) $p['minutes_left'] % 60) ?></small>
                        </div>
                        <form class="lt-inline-form" method="POST" action="index.php?page=family_invite_cancel">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="family_id" value="<?= $fid ?>">
                            <input type="hidden" name="invitation_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $t['fam_btn_cancel_invite'] ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <?php if ($isManager): ?>
            <!-- Inviter UNIQUEMENT avec le nom + le code privé de la personne -->
            <div class="lt-card lt-card-compact mb-4">
                <h4 class="title-color mb-2"><i class="icofont-ui-add mr-2"></i><?= $t['fam_invite_title'] ?></h4>
                <p class="small mb-3"><?= sprintf($t['fam_invite_text'], FamilyController::INVITE_HOURS) ?></p>
                <?php if ($tagLock > 0): ?>
                    <div class="alert alert-warning"><?= sprintf($t['fam_err_tag_locked'], (int) ceil($tagLock / 60)) ?></div>
                <?php endif; ?>
                <form class="appoinment-form" method="POST" action="index.php?page=family_invite" <?= validationAttrs($t) ?>>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="family_id" value="<?= $fid ?>">
                    <div class="form-group">
                        <label for="inv_nom"><?= $t['fam_invite_name'] ?></label>
                        <input type="text" id="inv_nom" name="nom" class="form-control" required data-rule="nom" maxlength="100"
                               autocomplete="off" value="<?= e($old['invite_nom'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-5">
                            <label for="inv_tag"><?= $t['fam_invite_tag'] ?></label>
                            <input type="text" id="inv_tag" name="tag" class="form-control lt-tag-input" required data-rule="tag"
                                   maxlength="5" inputmode="numeric" autocomplete="off" placeholder="#0000" value="<?= e($old['invite_tag'] ?? '') ?>">
                        </div>
                        <div class="form-group col-7">
                            <label for="inv_rel"><?= $t['fam_relation'] ?></label>
                            <select id="inv_rel" name="relation" class="form-control" required>
                                <?php foreach (FamilyController::RELATIONS as $r): ?>
                                    <option value="<?= $r ?>" <?= ($old['invite_relation'] ?? '') === $r ? 'selected' : '' ?>><?= $t['fam_rel_' . $r] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if (!empty($dependents)): ?>
                        <div class="form-group">
                            <label for="inv_link"><?= $t['fam_invite_link'] ?></label>
                            <select id="inv_link" name="link_member" class="form-control">
                                <option value="0"><?= $t['fam_invite_link_none'] ?></option>
                                <?php foreach ($dependents as $d): ?>
                                    <option value="<?= $d->getId() ?>"><?= e($d->getNom()) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted"><?= $t['fam_invite_link_help'] ?></small>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-main btn-round-full btn-block" <?= $tagLock > 0 ? 'disabled' : '' ?>><?= $t['fam_btn_invite'] ?> <i class="icofont-paper-plane ml-2"></i></button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Mon consentement : ce que je partage avec cette famille -->
        <div class="lt-card lt-card-compact mb-4">
            <h4 class="title-color mb-2"><i class="icofont-lock mr-2"></i><?= $t['fam_my_shares'] ?></h4>
            <p class="small mb-3"><?= $t['fam_share_note'] ?></p>
            <form method="POST" action="index.php?page=family_shares">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="family_id" value="<?= $fid ?>">
                <div class="lt-fam-shares mb-3">
                    <?php foreach (FamilyController::SHARES as $type): ?>
                        <label class="lt-fam-check"><input type="checkbox" name="share[<?= $type ?>]" value="1" <?= $me->shares($type) ? 'checked' : '' ?>> <?= $t['fam_share_' . $type] ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-main btn-round-full btn-sm"><?= $t['btn_save'] ?? $t['btn_update'] ?></button>
            </form>
        </div>

        <?php if ($isManager): ?>
            <div class="lt-card lt-card-compact mb-4">
                <h4 class="title-color mb-3"><i class="icofont-gear mr-2"></i><?= $t['fam_settings'] ?></h4>
                <form class="appoinment-form mb-3" method="POST" action="index.php?page=family_rename" <?= validationAttrs($t) ?>>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="family_id" value="<?= $fid ?>">
                    <label for="ren_nom"><?= $t['fam_name'] ?></label>
                    <div class="d-flex lt-gap">
                        <input type="text" id="ren_nom" name="nom" class="form-control" required data-rule="famille" maxlength="60" value="<?= e($family->getNom()) ?>">
                        <button type="submit" class="btn btn-main btn-round-full"><?= $t['fam_btn_rename'] ?></button>
                    </div>
                </form>
                <form method="POST" action="index.php?page=family_delete" data-confirm="<?= e($t['fam_confirm_delete']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="family_id" value="<?= $fid ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="icofont-trash mr-1"></i><?= $t['fam_btn_delete'] ?></button>
                </form>
                <p class="lt-note mt-2 mb-0"><?= $t['fam_manager_leave_note'] ?></p>
            </div>
        <?php else: ?>
            <form method="POST" action="index.php?page=family_leave" data-confirm="<?= e($t['fam_confirm_leave']) ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="family_id" value="<?= $fid ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="icofont-logout mr-1"></i><?= $t['fam_btn_leave'] ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
