/**
 * LabTrack - validation des formulaires côté client (JavaScript)
 * Utilisée par le frontoffice (inscription, connexion, profil) et le backoffice
 * (ajout / modification d'un compte).
 *
 * Fonctionnement :
 *  - le formulaire porte l'attribut data-validate (et novalidate pour remplacer
 *    les bulles du navigateur) ainsi que les messages traduits data-msg-* ;
 *  - chaque champ déclare ses règles : required, data-rule="nom|email|password|photo",
 *    data-match="id_du_champ_a_recopier" ;
 *  - les erreurs s'affichent sous le champ (classes Bootstrap is-invalid / invalid-feedback).
 *
 * C'est un confort pour l'utilisateur : le serveur (AuthController) revérifie
 * toujours tout, car le JavaScript peut être désactivé ou contourné.
 */
(function () {
  'use strict';

  // Mêmes règles que le PHP (AuthController::isValidNom, filter_var, strlen >= 8)
  var NOM_RE = /^\p{L}[\p{L}\s'\-]{1,99}$/u;
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var MIN_PASSWORD = 8;
  // Même règle que AuthController::updateAvatar (le serveur lit en plus le vrai contenu du fichier)
  var PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  var PHOTO_MAX_BYTES = 2 * 1024 * 1024;
  // Espace famille (mêmes règles que FamilyController) : code privé, nom de famille, date passée
  var TAG_RE = /^#?\d{4}$/;
  var FAMILLE_RE = /^[\p{L}\p{N}][\p{L}\p{N}\s'\-]{1,59}$/u;
  // Rappels & visites (mêmes règles que RappelController)
  var LABEL_RE = /^[\p{L}\p{N}][\p{L}\p{N}\s'’().,+\/%:-]{1,149}$/u;
  var TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/;
  var DATE_RE = /^\d{4}-\d{2}-\d{2}$/;

  function messageFor(field, form) {
    var value = field.value.trim();
    var msg = function (key) { return form.getAttribute('data-msg-' + key) || ''; };

    if (field.hasAttribute('required') && value === '') {
      return msg('required');
    }
    if (value === '') {
      // champ facultatif vide (ex : nouveau mot de passe en modification) :
      // seule la confirmation doit alors rester vide elle aussi
      var source = field.getAttribute('data-match');
      if (source) {
        var other = form.querySelector('#' + source);
        if (other && other.value !== '') { return msg('match'); }
      }
      return '';
    }
    switch (field.getAttribute('data-rule')) {
      case 'nom':
        if (!NOM_RE.test(value)) { return msg('nom'); }
        break;
      case 'email':
        if (!EMAIL_RE.test(value)) { return msg('email'); }
        break;
      case 'password':
        if (field.value.length < MIN_PASSWORD) { return msg('length'); }
        break;
      case 'tag':
        if (!TAG_RE.test(value)) { return msg('tag'); }
        break;
      case 'famille':
        if (!FAMILLE_RE.test(value)) { return msg('famille'); }
        break;
      case 'past-date':
        // AAAA-MM-JJ : la comparaison de chaînes suffit ; pas dans le futur, pas plus de 120 ans
        var today = new Date().toISOString().slice(0, 10);
        var oldest = (new Date().getFullYear() - 120) + today.slice(4);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value > today || value < oldest) { return msg('past-date'); }
        break;
      case 'label':
        if (!LABEL_RE.test(value)) { return msg('label'); }
        break;
      case 'time':
        if (!TIME_RE.test(value)) { return msg('time'); }
        break;
      case 'date':
        if (!DATE_RE.test(value) || isNaN(new Date(value).getTime())) { return msg('date'); }
        break;
      case 'count':
        // nombre entier entre les attributs min et max du champ (stock, unités par prise)
        var n = Number(value);
        var min = field.hasAttribute('min') ? Number(field.getAttribute('min')) : -Infinity;
        var max = field.hasAttribute('max') ? Number(field.getAttribute('max')) : Infinity;
        if (!/^\d+$/.test(value) || n < min || n > max) { return msg('count'); }
        break;
      case 'photo':
        var file = field.files && field.files[0];
        if (file && PHOTO_TYPES.indexOf(file.type) === -1) { return msg('photo-type'); }
        if (file && file.size > PHOTO_MAX_BYTES) { return msg('photo-size'); }
        break;
    }
    // data-after="id" : la date doit être le même jour ou après celle de l'autre champ (date de fin)
    var afterId = field.getAttribute('data-after');
    if (afterId) {
      var start = form.querySelector('#' + afterId);
      if (start && start.value !== '' && value < start.value) { return msg('after'); }
    }
    var matchId = field.getAttribute('data-match');
    if (matchId) {
      var target = form.querySelector('#' + matchId);
      if (target && target.value !== field.value) { return msg('match'); }
    }
    return '';
  }

  function feedbackFor(field) {
    var next = field.nextElementSibling;
    if (next && next.classList.contains('invalid-feedback')) { return next; }
    var div = document.createElement('div');
    div.className = 'invalid-feedback';
    field.insertAdjacentElement('afterend', div);
    return div;
  }

  function check(field, form) {
    var message = messageFor(field, form);
    var feedback = feedbackFor(field);
    feedback.textContent = message;
    field.classList.toggle('is-invalid', message !== '');
    field.setAttribute('aria-invalid', message !== '' ? 'true' : 'false');
    return message === '';
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    var fields = form.querySelectorAll('input[required], input[data-rule], input[data-match], textarea[required], select[required]');

    // Vérification au fil de la saisie, une fois que l'utilisateur a quitté le champ
    fields.forEach(function (field) {
      field.addEventListener('blur', function () { check(field, form); });
      field.addEventListener('change', function () { if (field.type === 'file') { check(field, form); } });
      field.addEventListener('input', function () {
        if (field.classList.contains('is-invalid')) { check(field, form); }
        // la confirmation dépend du premier mot de passe
        form.querySelectorAll('[data-match="' + field.id + '"]').forEach(function (c) {
          if (c.classList.contains('is-invalid') || c.value !== '') { check(c, form); }
        });
      });
    });

    // Blocage de l'envoi tant qu'un champ est invalide
    form.addEventListener('submit', function (e) {
      var firstInvalid = null;
      fields.forEach(function (field) {
        if (!check(field, form) && !firstInvalid) { firstInvalid = field; }
      });
      if (firstInvalid) {
        e.preventDefault();
        firstInvalid.focus();
      }
    });
  });
})();
