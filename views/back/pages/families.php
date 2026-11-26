<?php
/**
 * views/back/pages/families.php
 * Backoffice - Espace famille : statistiques, liste des familles, recherche, suppression.
 * L'administrateur voit les familles et leurs membres, JAMAIS leurs données de santé.
 * Variables : $families, $query, $stats, $flash
 */
$svg = fn(string $d): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
?>
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <nav aria-label="Breadcrumb"><ol class="crumbs"><li><a href="index.php?page=admin"><?= $t['admin_dashboard'] ?></a></li><li aria-current="page"><?= $t['fam_admin_title'] ?></li></ol></nav>
        <h1 class="page-title"><?= $t['fam_admin_title'] ?></h1>
        <p class="page-sub"><?= $t['fam_admin_sub'] ?></p>
    </div>
</div>

<section class="kpi-grid" aria-label="<?= e($t['fam_admin_title']) ?>">
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-teal"><?= $svg('<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>') ?></span><span class="kpi-label"><?= $t['fam_admin_stat_families'] ?></span></div>
        <p class="kpi-value"><?= (int) $stats['families'] ?></p>
        <p class="kpi-note">LabTrack</p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-green"><?= $svg('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>') ?></span><span class="kpi-label"><?= $t['fam_admin_stat_average'] ?></span></div>
        <p class="kpi-value"><?= e((string) $stats['average']) ?></p>
        <p class="kpi-note"><?= $t['fam_admin_stat_average_note'] ?></p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-info"><?= $svg('<circle cx="12" cy="7" r="3"/><path d="M6 21v-1a6 6 0 0 1 12 0v1"/>') ?></span><span class="kpi-label"><?= $t['fam_admin_stat_dependents'] ?></span></div>
        <p class="kpi-value"><?= (int) $stats['dependents'] ?></p>
        <p class="kpi-note"><?= $t['fam_admin_stat_dependents_note'] ?></p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-amber"><?= $svg('<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>') ?></span><span class="kpi-label"><?= $t['fam_admin_stat_pending'] ?></span></div>
        <p class="kpi-value"><?= (int) $stats['pending'] ?></p>
        <p class="kpi-note"><?= sprintf($t['fam_admin_stat_pending_note'], FamilyController::INVITE_HOURS) ?></p>
    </div>
</section>

<section class="panel" aria-labelledby="famTitle">
    <div class="panel-head panel-head-flush">
        <h2 class="panel-title visually-hidden" id="famTitle"><?= $t['fam_admin_title'] ?></h2>
        <form class="filter-bar" role="search" method="get" action="index.php">
            <input type="hidden" name="page" value="admin_families">
            <div class="filter-search">
                <label for="fSearch" class="visually-hidden"><?= $t['fam_admin_search'] ?></label>
                <?= $svg('<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>') ?>
                <input id="fSearch" type="search" name="q" class="form-control" value="<?= e($query) ?>" placeholder="<?= e($t['fam_admin_search']) ?>" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary"><?= $t['btn_search'] ?></button>
            <a class="btn btn-link btn-clear" href="index.php?page=admin_families"><?= $t['btn_reset'] ?></a>
        </form>
    </div>
    <div class="table-wrap">
        <table class="table-data table-stack">
            <caption class="visually-hidden"><?= $t['fam_admin_title'] ?></caption>
            <thead>
            <tr>
                <th scope="col"><?= $t['fam_name'] ?></th>
                <th scope="col"><?= $t['fam_role_manager'] ?></th>
                <th scope="col"><?= $t['fam_members_title'] ?></th>
                <th scope="col"><?= $t['fam_admin_stat_dependents'] ?></th>
                <th scope="col"><?= $t['col_created'] ?></th>
                <th scope="col"><?= $t['col_actions'] ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($families)): ?>
                <tr><td colspan="6" class="text-center py-4"><?= $t['no_results'] ?></td></tr>
            <?php endif; ?>
            <?php foreach ($families as $f): ?>
                <tr>
                    <td class="cell-main" data-label="<?= $t['fam_name'] ?>"><span class="person-name"><?= e($f['nom']) ?></span> <span class="person-meta">#<?= (int) $f['id'] ?></span></td>
                    <td data-label="<?= $t['fam_role_manager'] ?>"><?= e($f['gestionnaire'] ?? '—') ?></td>
                    <td data-label="<?= $t['fam_members_title'] ?>"><?= (int) $f['nb_membres'] ?></td>
                    <td data-label="<?= $t['fam_admin_stat_dependents'] ?>"><?= (int) $f['nb_charges'] ?></td>
                    <td data-label="<?= $t['col_created'] ?>"><?= e(substr($f['created_at'], 0, 10)) ?></td>
                    <td class="cell-actions">
                        <div class="lt-actions">
                            <a class="btn btn-sm btn-outline-primary" href="index.php?page=admin_family&amp;id=<?= (int) $f['id'] ?>"><?= $t['fam_admin_view'] ?></a>
                            <form class="lt-inline-form" method="POST" action="index.php?page=admin_family_delete"
                                  data-confirm="<?= e(sprintf($t['fam_admin_confirm_delete'], $f['nom'])) ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="family_id" value="<?= (int) $f['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= $t['btn_delete'] ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
