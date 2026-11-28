/**
 * LabTrack - module Analyses sanguines
 *  - page de relecture : ajouter / retirer une ligne de valeur
 *  - page d'envoi : le bouton indique que la lecture est en cours (quelques secondes)
 * (fichier externe : la CSP interdit les scripts dans la page)
 */
(function () {
  'use strict';

  var tbody = document.querySelector('#bilan-rows tbody');
  var tpl = document.getElementById('bilan-row-template');
  var addBtn = document.getElementById('bilan-add-row');

  if (tbody && tpl && addBtn) {
    var next = tbody.querySelectorAll('tr').length;
    addBtn.addEventListener('click', function () {
      var wrap = document.createElement('tbody');
      wrap.innerHTML = tpl.innerHTML.replace(/__I__/g, String(next++));
      var row = wrap.firstElementChild;
      tbody.appendChild(row);
      var first = row.querySelector('input');
      if (first) { first.focus(); }
    });
    tbody.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-remove-row]');
      if (btn) { btn.closest('tr').remove(); }
    });
  }

  var upload = document.querySelector('form[data-bilan-upload]');
  if (upload) {
    upload.addEventListener('submit', function () {
      var btn = upload.querySelector('button[type="submit"]');
      if (btn && upload.checkValidity()) {
        btn.disabled = true;
        btn.textContent = btn.getAttribute('data-busy') || '...';
      }
    });
  }
})();
