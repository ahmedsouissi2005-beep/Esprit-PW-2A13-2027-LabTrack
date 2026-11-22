/**
 * LabTrack Admin - comportements du layout du backoffice.
 * Repris de la partie "3. LAYOUT" de js/main.js du template Registrar
 * (sans les données de démonstration de l'école, remplacées par nos données PHP).
 */
(function () {
  'use strict';

  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* Mode clair / sombre */
  function initTheme() {
    var root = document.documentElement;
    var buttons = $$('[data-theme-toggle]');
    if (!buttons.length) return;
    var sync = function () {
      var dark = root.getAttribute('data-bs-theme') === 'dark';
      buttons.forEach(function (b) { b.setAttribute('aria-pressed', String(dark)); });
    };
    buttons.forEach(function (b) {
      b.addEventListener('click', function () {
        var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('labtrack-theme', next); } catch (e) { /* stockage indisponible */ }
        sync();
      });
    });
    sync();
  }

  /* Menu latéral réductible (grand écran) et fermeture du menu mobile */
  function initSidebar() {
    var btn = $('#sidebarCollapse');
    var root = document.documentElement;
    if (btn) {
      var sync = function () {
        btn.setAttribute('aria-expanded', String(!root.classList.contains('sidebar-collapsed')));
      };
      var anim = 0;
      btn.addEventListener('click', function () {
        root.classList.add('sidebar-anim');
        clearTimeout(anim);
        anim = setTimeout(function () { root.classList.remove('sidebar-anim'); }, 320);
        root.classList.toggle('sidebar-collapsed');
        try {
          localStorage.setItem('labtrack-sidebar', root.classList.contains('sidebar-collapsed') ? 'collapsed' : 'open');
        } catch (e) { /* stockage indisponible */ }
        sync();
      });
      sync();
    }
    var sidebar = $('#sidebar');
    if (sidebar) {
      sidebar.addEventListener('click', function (e) {
        if (!e.target.closest('a') || window.innerWidth >= 992 || !window.bootstrap) return;
        var oc = window.bootstrap.Offcanvas.getInstance(sidebar);
        if (oc) oc.hide();
      });
    }
  }

  /* Raccourci clavier "/" pour aller dans la recherche */
  function initGlobalSearch() {
    var input = $('#globalSearch');
    if (!input) return;
    document.addEventListener('keydown', function (e) {
      if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
      if (e.target.closest('input, textarea, select, [contenteditable="true"]')) return;
      e.preventDefault();
      input.focus();
      input.select();
    });
  }

  var init = function () {
    initTheme();
    initSidebar();
    initGlobalSearch();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
