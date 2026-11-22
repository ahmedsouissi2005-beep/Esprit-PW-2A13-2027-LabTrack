<?php
/**
 * views/back/partials/scripts.php
 * Scripts communs + scripts propres à une page ($extraScripts), puis fermeture du document.
 */
?>
<script src="assets/back/js/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/back/js/admin.js"></script>
<?php if (!empty($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
