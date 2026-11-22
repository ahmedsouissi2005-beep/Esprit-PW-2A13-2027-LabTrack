/**
 * LabTrack Admin - page Utilisateurs.
 * Demande une confirmation avant de supprimer un compte.
 * (Confort pour l'utilisateur seulement : la vraie sécurité reste côté serveur
 *  avec requireRole('admin') et le jeton CSRF.)
 */
(function () {
  'use strict';
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });
})();
