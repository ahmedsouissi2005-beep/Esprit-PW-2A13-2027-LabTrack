<?php
/**
 * views/front/family/messages.php - onglet "Messages"
 * Discussion privée de la famille (visible seulement par ses comptes membres).
 * Chacun peut supprimer ses messages ; le gestionnaire peut supprimer tous les messages.
 * Variables : $messages (+ celles de family_view.php)
 */
$myUserId = (int) $_SESSION['user_id'];
?>
<div class="lt-card lt-card-compact">
    <h4 class="title-color mb-3"><i class="icofont-chat mr-2"></i><?= $t['fam_messages_title'] ?></h4>
    <div class="lt-msg-list">
        <?php if (empty($messages)): ?>
            <p class="text-muted"><?= $t['fam_no_messages'] ?></p>
        <?php endif; ?>
        <?php foreach ($messages as $msg):
            $mine = (int) $msg['user_id'] === $myUserId; ?>
            <div class="lt-msg <?= $mine ? 'is-mine' : '' ?>">
                <?= adminAvatar((int) $msg['user_id'], $msg['nom'], $msg['avatar'], 'lt-msg-avatar') ?>
                <div class="lt-msg-bubble">
                    <div class="lt-msg-meta"><strong><?= e($msg['nom']) ?></strong> · <?= e(substr($msg['created_at'], 0, 16)) ?></div>
                    <div class="lt-msg-text"><?= nl2br(e($msg['contenu'])) ?></div>
                    <?php if ($mine || $isManager): ?>
                        <form class="lt-msg-delete" method="POST" action="index.php?page=family_message_delete" data-confirm="<?= e($t['fam_confirm_message']) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="family_id" value="<?= $family->getId() ?>">
                            <input type="hidden" name="message_id" value="<?= (int) $msg['id'] ?>">
                            <button type="submit" class="btn btn-link btn-sm p-0"><?= $t['btn_delete'] ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form class="mt-3" method="POST" action="index.php?page=family_message" <?= validationAttrs($t) ?>>
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="family_id" value="<?= $family->getId() ?>">
        <label for="contenu" class="sr-only"><?= $t['fam_message_label'] ?></label>
        <textarea id="contenu" name="contenu" class="form-control mb-2" rows="3" maxlength="1000" required
                  placeholder="<?= e($t['fam_message_placeholder']) ?>"><?= e($old['contenu'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-main btn-round-full"><?= $t['fam_btn_send'] ?> <i class="icofont-paper-plane ml-2"></i></button>
    </form>
</div>
