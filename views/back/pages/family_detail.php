<?php
/**
 * views/back/pages/family_detail.php
 * Backoffice - une famille : ses membres (nom, type, lien, rôle) et ses invitations en
 * attente. Aucune donnée de santé (analyses, rappels, carte d'urgence, messages) n'est affichée.
 * Variables : $family, $members, $pending, $messageCount, $flash
 */
$roleStatus = ['manager' => 'status-warning', 'caregiver' => 'status-info', 'member' => 'status-success'];
?>
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <nav aria-label="Breadcrumb"><ol class="crumbs"><li><a href="index.php?page=admin"><?= $t['admin_dashboard'] ?></a></li><li><a href="index.php?page=admin_families"><?= $t['fam_admin_title'] ?></a></li><li aria-current="page"><?= e($family->getNom()) ?></li></ol></nav>
        <h1 class="page-title"><?= e($family->getNom()) ?></h1>
        <p class="page-sub"><?= sprintf($t['fam_admin_detail_sub'], count($members), count($pending), $messageCount, e(substr($family->getCreatedAt(), 0, 10))) ?></p>
    </div>
    <div class="page-actions">
        <form method="POST" action="index.php?page=admin_family_delete" data-confirm="<?= e(sprintf($t['fam_admin_confirm_delete'], $family->getNom())) ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="family_id" value="<?= $family->getId() ?>">
            <button type="submit" class="btn btn-outline-danger"><?= $t['fam_btn_delete'] ?></button>
        </form>
    </div>
</div>

<div class="alert alert-info"><?= $t['fam_admin_privacy'] ?></div>

<section class="panel">
    <div class="table-wrap">
        <table class="table-data table-stack">
            <caption class="visually-hidden"><?= $t['fam_members_title'] ?></caption>
            <thead>
            <tr>
                <th scope="col"><?= $t['nom'] ?></th>
                <th scope="col"><?= $t['fam_admin_type'] ?></th>
                <th scope="col"><?= $t['fam_relation'] ?></th>
                <th scope="col"><?= $t['role'] ?></th>
                <th scope="col"><?= $t['fam_admin_joined'] ?></th>
                <th scope="col"><?= $t['col_actions'] ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m): /** @var MembreModel $m */ ?>
                <tr>
                    <td class="cell-main" data-label="<?= $t['nom'] ?>">
                        <div class="person">
                            <?= adminAvatar((int) $m->getUserId(), $m->getNom(), $m->getAvatar(), 'av-' . (($m->getId() % 5) + 1)) ?>
                            <span class="person-name"><?= e($m->getNom()) ?></span>
                        </div>
                    </td>
                    <td data-label="<?= $t['fam_admin_type'] ?>"><?= $m->isDependent() ? $t['fam_dependent'] : $t['fam_admin_account'] ?></td>
                    <td data-label="<?= $t['fam_relation'] ?>"><?= $t['fam_rel_' . $m->getRelation()] ?? '' ?></td>
                    <td data-label="<?= $t['role'] ?>"><span class="status <?= $roleStatus[$m->getRole()] ?? 'status-neutral' ?>"><?= $t['fam_role_' . $m->getRole()] ?></span></td>
                    <td data-label="<?= $t['fam_admin_joined'] ?>"><?= e(substr($m->getJoinedAt(), 0, 10)) ?></td>
                    <td class="cell-actions">
                        <?php if ($m->getRole() !== 'manager'): ?>
                            <form class="lt-inline-form" method="POST" action="index.php?page=admin_family_member_remove"
                                  data-confirm="<?= e(sprintf($t['fam_confirm_remove'], $m->getNom())) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="family_id" value="<?= $family->getId() ?>">
                                <input type="hidden" name="member_id" value="<?= $m->getId() ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= $t['fam_remove'] ?></button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
