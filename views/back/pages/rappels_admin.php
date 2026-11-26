<?php
/**
 * views/back/pages/rappels_admin.php
 * Backoffice - Rappels & visites : chiffres globaux et activité par compte.
 * Par respect du secret médical, ni le nom des médicaments ni le motif des rendez-vous
 * ne sont affichés ici.
 * Variables : $stats, $users, $flash
 */
$svg = fn(string $d): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
?>
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <nav aria-label="Breadcrumb"><ol class="crumbs"><li><a href="index.php?page=admin"><?= $t['admin_dashboard'] ?></a></li><li aria-current="page"><?= $t['mod_rappels'] ?></li></ol></nav>
        <h1 class="page-title"><?= $t['rap_admin_title'] ?></h1>
        <p class="page-sub"><?= $t['rap_admin_sub'] ?></p>
    </div>
</div>

<section class="kpi-grid" aria-label="<?= e($t['rap_admin_title']) ?>">
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-teal"><?= $svg('<path d="M10.5 20.5 3.5 13.5a5 5 0 0 1 7-7l7 7a5 5 0 0 1-7 7z"/><path d="m8.5 8.5 7 7"/>') ?></span><span class="kpi-label"><?= $t['rap_admin_reminders'] ?></span></div>
        <p class="kpi-value"><?= (int) $stats['reminders'] ?></p>
        <p class="kpi-note"><?= sprintf($t['rap_admin_dependents'], (int) $stats['dependents']) ?></p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-green"><?= $svg('<path d="M20 6 9 17l-5-5"/>') ?></span><span class="kpi-label"><?= $t['rap_admin_adherence'] ?></span></div>
        <p class="kpi-value"><?= (int) $stats['adherence'] ?>%</p>
        <p class="kpi-note"><?= $t['rap_admin_adherence_note'] ?></p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-info"><?= $svg('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>') ?></span><span class="kpi-label"><?= $t['rap_admin_upcoming'] ?></span></div>
        <p class="kpi-value"><?= (int) $stats['upcoming'] ?></p>
        <p class="kpi-note"><?= $t['rap_admin_upcoming_note'] ?></p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-amber"><?= $svg('<path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/>') ?></span><span class="kpi-label"><?= $t['rap_admin_sent'] ?></span></div>
        <p class="kpi-value"><?= (int) $stats['sent'] ?></p>
        <p class="kpi-note"><?= $t['rap_admin_sent_note'] ?></p>
    </div>
</section>

<div class="alert alert-info"><?= $t['rap_admin_privacy'] ?></div>

<section class="panel">
    <div class="table-wrap">
        <table class="table-data table-stack">
            <caption class="visually-hidden"><?= $t['rap_admin_title'] ?></caption>
            <thead>
            <tr>
                <th scope="col"><?= $t['nom'] ?></th>
                <th scope="col"><?= $t['rap_admin_col_reminders'] ?></th>
                <th scope="col"><?= $t['rap_admin_col_rdv'] ?></th>
                <th scope="col"><?= $t['rap_admin_col_last'] ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="4" class="text-center py-4"><?= $t['no_results'] ?></td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="cell-main" data-label="<?= $t['nom'] ?>">
                        <div class="person"><?= adminAvatar((int) $u['id'], $u['nom'], $u['avatar'], 'av-' . (((int) $u['id'] % 5) + 1)) ?><span class="person-name"><?= e($u['nom']) ?></span></div>
                    </td>
                    <td data-label="<?= $t['rap_admin_col_reminders'] ?>"><?= (int) $u['rappels'] ?></td>
                    <td data-label="<?= $t['rap_admin_col_rdv'] ?>"><?= (int) $u['rdv'] ?></td>
                    <td data-label="<?= $t['rap_admin_col_last'] ?>"><?= $u['derniere'] ? e(substr($u['derniere'], 0, 16)) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
