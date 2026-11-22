<?php
/**
 * views/front/includes/spinner.php
 * Écran de chargement affiché juste après <body>, masqué par assets/front/js/labtrack.js
 */
?>
<div id="spinner" class="lt-spinner show" role="status">
    <div class="lt-spinner-ring"></div>
    <span class="sr-only"><?= $t['loading'] ?></span>
</div>
