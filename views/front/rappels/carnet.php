<?php
/**
 * views/front/rappels/carnet.php - onglet "Carnet médical"
 * Notes personnelles et privées du compte connecté (observations, questions pour le médecin),
 * éventuellement associées à un de ses rendez-vous. Seul son auteur les voit.
 * Variables : $notes, $appointmentsForNotes (+ celles de rappels.php)
 */
?>
<div class="lt-carnet-head">
  <div><span class="lt-carnet-eyebrow"><?= e($t['rap_personal_space']) ?></span><h2><?= e($t['rap_carnet_title']) ?></h2>
  <p><?= e($t['rap_carnet_text']) ?></p></div>
  <span class="lt-carnet-icon" aria-hidden="true">✎</span>
</div>
<div class="lt-carnet-grid">
  <section class="lt-carnet-form">
    <h3>＋ <?= e($t['rap_carnet_add']) ?></h3>
    <form method="post" action="index.php?page=rappels&amp;tab=carnet" <?= validationAttrs($t) ?>>
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="carnet_action" value="add">
      <label for="carnet-titre"><?= e($t['rap_carnet_note_title']) ?></label>
      <input id="carnet-titre" name="titre" maxlength="150" required placeholder="<?= e($t['rap_carnet_note_title_ph']) ?>">
      <label for="carnet-rdv"><?= e($t['rap_carnet_link']) ?></label>
      <select id="carnet-rdv" name="rendez_vous_id">
        <option value="0"><?= e($t['rap_carnet_link_none']) ?></option>
        <?php foreach (($appointmentsForNotes ?? []) as $rdv): ?>
          <option value="<?= (int) $rdv['id'] ?>"><?= e(date('d/m/Y H:i', strtotime($rdv['date_rdv']))) ?> · <?= e($rdv['titre']) ?><?= !empty($rdv['medecin']) ? ' — ' . e($rdv['medecin']) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <label for="carnet-contenu"><?= e($t['rap_carnet_content']) ?></label>
      <textarea id="carnet-contenu" name="contenu" rows="7" maxlength="<?= RappelController::NOTE_MAX ?>" required placeholder="<?= e($t['rap_carnet_content_ph']) ?>"></textarea>
      <button type="submit" class="btn btn-main"><?= e($t['rap_carnet_save']) ?></button>
    </form>
  </section>
  <section class="lt-carnet-list">
    <div class="lt-carnet-list-head"><h3><?= e($t['rap_carnet_list']) ?></h3><span><?= e(sprintf($t['rap_carnet_count'], count($notes))) ?></span></div>
    <?php if (!empty($notes)): foreach ($notes as $note): ?>
      <article class="lt-carnet-note">
        <div class="lt-carnet-note-top"><h4><?= e($note['titre']) ?></h4><time datetime="<?= e($note['created_at']) ?>"><?= e(date('d/m/Y H:i', strtotime($note['created_at']))) ?></time></div>
        <?php if (!empty($note['rendez_vous_id']) && !empty($note['rdv_titre'])): ?>
          <div class="lt-carnet-linked"><span aria-hidden="true">⌁</span><div><strong><?= e($t['rap_carnet_linked']) ?></strong><small><?= e($note['rdv_titre']) ?> · <?= e(date('d/m/Y H:i', strtotime($note['rdv_date']))) ?><?= !empty($note['rdv_medecin']) ? ' · ' . e($note['rdv_medecin']) : '' ?></small></div></div>
        <?php endif; ?>
        <p><?= nl2br(e($note['contenu'])) ?></p>
        <!-- data-confirm : confirmation par labtrack.js (la CSP bloque les onsubmit="..." inline) -->
        <form method="post" action="index.php?page=rappels&amp;tab=carnet" data-confirm="<?= e($t['rap_carnet_confirm_delete']) ?>">
          <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
          <input type="hidden" name="carnet_action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"><?= e($t['btn_delete']) ?></button>
        </form>
      </article>
    <?php endforeach; else: ?>
      <div class="lt-carnet-empty"><span aria-hidden="true">📝</span><strong><?= e($t['rap_carnet_empty_title']) ?></strong><p><?= e($t['rap_carnet_empty_text']) ?></p></div>
    <?php endif; ?>
  </section>
</div>
