/**
 * LabTrack - comportements du frontoffice
 * Masque le spinner (includes/spinner.php) une fois la page chargée.
 */
(function () {
  'use strict';

  function hideSpinner() {
    var spinner = document.getElementById('spinner');
    if (spinner) {
      spinner.classList.remove('show');
    }
  }

  if (document.readyState === 'complete') {
    hideSpinner();
  } else {
    window.addEventListener('load', hideSpinner);
  }
  // Sécurité : le spinner ne reste jamais plus de 3 secondes
  setTimeout(hideSpinner, 3000);
})();
