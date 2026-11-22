<?php
/**
 * views/back/layout.php
 * Assemble les partials du backoffice dans le bon ordre (atelier, étape 8).
 * Reçoit $title, $currentPage et $content (préparés par Controller::render()).
 */
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/partials/header.php';
?>
<main id="main" class="app-content" tabindex="-1">
    <?= $content ?>
</main>
<?php
require __DIR__ . '/partials/footer.php';
require __DIR__ . '/partials/scripts.php';
