<?php
/**
 * views/back/pages/users.php
 * Gestion des utilisateurs : recherche, filtre par rôle, changement de rôle, suppression.
 * Variables : $heading, $admin, $users (UserModel[]), $query, $roleFilter, $flash
 */
$roleStatus = ['patient' => 'status-success', 'admin' => 'status-warning'];
$currentId = $admin ? $admin->getId() : 0;
?>
<?php if (!empty($flash)): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?>"><?= e($t[$flash['message']] ?? '') ?></div>
<?php endif; ?>

<div class="page-head">
    <div>
        <nav aria-label="Breadcrumb"><ol class="crumbs"><li><a href="index.php?page=admin"><?= $t['admin_dashboard'] ?></a></li><li aria-current="page"><?= $t['admin_users'] ?></li></ol></nav>
        <h1 class="page-title"><?= $t['all_users'] ?></h1>
        <p class="page-sub"><?= count($users) ?> <?= $t['results_count'] ?> · <?= $t['users_sub'] ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="index.php?page=admin_user_form">+ <?= $t['add_user'] ?></a>
    </div>
</div>

<section class="panel" aria-labelledby="dirTitle">
    <div class="panel-head panel-head-flush">
        <h2 class="panel-title visually-hidden" id="dirTitle"><?= $t['all_users'] ?></h2>
        <!-- Recherche côté serveur (GET) : requête préparée avec LIKE -->
        <form class="filter-bar" role="search" method="get" action="index.php">
            <input type="hidden" name="page" value="admin_users">
            <div class="filter-search">
                <label for="fSearch" class="visually-hidden"><?= $t['search_users'] ?></label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="fSearch" type="search" name="q" class="form-control" value="<?= e($query) ?>" placeholder="<?= $t['search_users'] ?>" autocomplete="off">
            </div>
            <div class="filter-field">
                <label for="fRole"><?= $t['filter_role'] ?></label>
                <select id="fRole" name="role" class="form-select">
                    <option value=""><?= $t['all_roles'] ?></option>
                    <?php foreach (AuthController::ROLES as $r): ?>
                        <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= $t['role_' . $r] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><?= $t['btn_search'] ?></button>
            <a class="btn btn-link btn-clear" href="index.php?page=admin_users"><?= $t['btn_reset'] ?></a>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table-data table-stack">
            <caption class="visually-hidden"><?= $t['all_users'] ?></caption>
            <thead>
            <tr>
                <th scope="col"><?= $t['nom'] ?></th>
                <th scope="col"><?= $t['email'] ?></th>
                <th scope="col"><?= $t['role'] ?></th>
                <th scope="col"><?= $t['language'] ?></th>
                <th scope="col"><?= $t['col_created'] ?></th>
                <th scope="col"><?= $t['col_actions'] ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center py-4"><?= $t['no_results'] ?></td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): /** @var UserModel $u */ ?>
                <tr>
                    <td class="cell-main" data-label="<?= $t['nom'] ?>">
                        <div class="person">
                            <?= adminAvatar($u->getId(), $u->getNom(), $u->getAvatar(), 'av-' . (($u->getId() % 5) + 1)) ?>
                            <div>
                                <span class="person-name"><?= e($u->getNom()) ?> <?php if ($u->getId() === $currentId): ?><small class="text-muted"><?= $t['you'] ?></small><?php endif; ?></span>
                                <span class="person-meta">#<?= $u->getId() ?></span>
                            </div>
                        </div>
                    </td>
                    <td data-label="<?= $t['email'] ?>"><?= e($u->getEmail()) ?></td>
                    <td data-label="<?= $t['role'] ?>"><span class="status <?= $roleStatus[$u->getRole()] ?? 'status-neutral' ?>"><?= $t['role_' . $u->getRole()] ?? e($u->getRole()) ?></span></td>
                    <td data-label="<?= $t['language'] ?>"><?= $t['lang_' . $u->getLangue()] ?? '' ?></td>
                    <td data-label="<?= $t['col_created'] ?>"><?= e(substr($u->getCreatedAt(), 0, 10)) ?></td>
                    <td class="cell-actions">
                        <?php if ($u->getId() !== $currentId): ?>
                            <div class="lt-actions">
                                <!-- Consulter / modifier le compte -->
                                <a class="btn btn-sm btn-outline-primary" href="index.php?page=admin_user_form&amp;id=<?= $u->getId() ?>"><?= $t['btn_edit'] ?></a>
                                <!-- Changer le rôle (POST + jeton CSRF) -->
                                <form class="lt-inline-form" method="POST" action="index.php?page=admin_role">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="user_id" value="<?= $u->getId() ?>">
                                    <label class="visually-hidden" for="role<?= $u->getId() ?>"><?= $t['role'] ?></label>
                                    <select id="role<?= $u->getId() ?>" name="role" class="form-select form-select-sm">
                                        <?php foreach (AuthController::ROLES as $r): ?>
                                            <option value="<?= $r ?>" <?= $u->getRole() === $r ? 'selected' : '' ?>><?= $t['role_' . $r] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary"><?= $t['btn_update'] ?></button>
                                </form>
                                <!-- Supprimer (POST + jeton CSRF, confirmation par users.js) -->
                                <form class="lt-inline-form" method="POST" action="index.php?page=admin_delete"
                                      data-confirm="<?= e($t['confirm_delete']) ?> <?= e($u->getNom()) ?> ?">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="user_id" value="<?= $u->getId() ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?= $t['btn_delete'] ?></button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Son propre compte : modification possible, mais pas le rôle ni la suppression -->
                            <a class="btn btn-sm btn-outline-primary" href="index.php?page=admin_user_form&amp;id=<?= $u->getId() ?>"><?= $t['btn_edit'] ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
