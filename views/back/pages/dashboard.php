<?php
/**
 * views/back/pages/dashboard.php
 * Contenu seul (sans head, sidebar, header ni footer) : il est placé dans layout.php.
 * Variables : $heading, $admin, $totalUsers, $totalPatients, $totalAdmins,
 *             $newUsers, $byLangue, $recentUsers, $flash
 */
$roleStatus = ['patient' => 'status-success', 'admin' => 'status-warning'];
$pct = fn(int $n): float => $totalUsers > 0 ? round($n * 100 / $totalUsers, 1) : 0;
$roles = ['patient' => $totalPatients, 'admin' => $totalAdmins];
?>
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <p class="eyebrow"><?= $t['admin_dashboard'] ?> · <?= date('d/m/Y') ?></p>
        <h1 class="page-title"><?= $t['hello'] ?> <?= e($admin ? $admin->getNom() : '') ?></h1>
        <p class="page-sub"><?= $t['dashboard_sub'] ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="index.php?page=admin_users"><?= $t['manage_users'] ?></a>
    </div>
</div>

<section class="kpi-grid" aria-label="Key figures">
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-teal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/></svg></span><span class="kpi-label"><?= $t['stat_total'] ?></span></div>
        <p class="kpi-value"><?= $totalUsers ?></p>
        <p class="kpi-note">LabTrack</p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span><span class="kpi-label"><?= $t['stat_patients'] ?></span></div>
        <p class="kpi-value"><?= $totalPatients ?></p>
        <p class="kpi-note"><strong><?= $pct($totalPatients) ?>%</strong> <?= $t['stat_share'] ?></p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg></span><span class="kpi-label"><?= $t['stat_new'] ?></span></div>
        <p class="kpi-value"><?= $newUsers ?></p>
        <p class="kpi-note"><?= $t['stat_new_note'] ?></p>
    </div>
    <div class="kpi">
        <div class="kpi-top"><span class="kpi-icon tone-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5z"/></svg></span><span class="kpi-label"><?= $t['stat_admins'] ?></span></div>
        <p class="kpi-value"><?= $totalAdmins ?></p>
        <p class="kpi-note"><strong><?= $pct($totalAdmins) ?>%</strong> <?= $t['stat_share'] ?></p>
    </div>
</section>

<div class="row g-3 g-xxl-4">
    <div class="col-xl-4 col-lg-6">
        <section class="panel h-100" aria-labelledby="roleTitle">
            <div class="panel-head"><div><h2 class="panel-title" id="roleTitle"><?= $t['by_role'] ?></h2></div></div>
            <div class="panel-body">
                <ul class="bar-list">
                    <?php foreach ($roles as $role => $count): ?>
                        <li>
                            <span class="bar-label"><?= $t['role_' . $role] ?></span>
                            <span class="meter <?= $role === 'patient' ? 'meter-good' : ($role === 'admin' ? 'meter-ok' : '') ?>" aria-hidden="true"><span style="width:<?= $pct($count) ?>%"></span></span>
                            <span class="bar-value"><?= $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
    <div class="col-xl-4 col-lg-6">
        <section class="panel h-100" aria-labelledby="langTitle">
            <div class="panel-head"><div><h2 class="panel-title" id="langTitle"><?= $t['by_language'] ?></h2></div></div>
            <div class="panel-body">
                <ul class="bar-list">
                    <?php foreach ($byLangue as $code => $count): ?>
                        <li>
                            <span class="bar-label"><?= $t['lang_' . $code] ?></span>
                            <span class="meter" aria-hidden="true"><span style="width:<?= $pct($count) ?>%"></span></span>
                            <span class="bar-value"><?= $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="panel h-100" aria-labelledby="recentTitle">
            <div class="panel-head"><div><h2 class="panel-title" id="recentTitle"><?= $t['recent_users'] ?></h2></div></div>
            <ul class="notice-list">
                <?php foreach ($recentUsers as $u): ?>
                    <li class="notice">
                        <?= adminAvatar($u->getId(), $u->getNom(), $u->getAvatar(), 'av-' . (($u->getId() % 5) + 1)) ?>
                        <div class="lt-grow">
                            <p class="person-name mb-0"><?= e($u->getNom()) ?></p>
                            <span class="person-meta"><?= e($u->getEmail()) ?></span>
                        </div>
                        <span class="status <?= $roleStatus[$u->getRole()] ?? 'status-neutral' ?>"><?= $t['role_' . $u->getRole()] ?? '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="panel-foot"><a class="link-arrow" href="index.php?page=admin_users"><?= $t['view_all'] ?> →</a></div>
        </section>
    </div>
</div>
