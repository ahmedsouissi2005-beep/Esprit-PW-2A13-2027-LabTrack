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

  // Photo de profil : un clic sur le rond ouvre le choix du fichier, puis la photo
  // est envoyée tout de suite (comme sur un réseau social). Le bouton "Enregistrer"
  // ne reste visible que si le JavaScript est désactivé.
  document.documentElement.classList.add('lt-js');
  document.querySelectorAll('input[type="file"][data-autosubmit]').forEach(function (input) {
    input.addEventListener('change', function () {
      // form-validation.js vérifie d'abord le type et la taille (même évènement)
      setTimeout(function () {
        if (!input.files.length || input.classList.contains('is-invalid')) { return; }
        var form = input.form;
        var picker = form.querySelector('.lt-avatar-picker');
        var reader = new FileReader();
        reader.onload = function () {
          // aperçu immédiat pendant l'envoi
          var img = picker.querySelector('img.lt-avatar');
          if (!img) {
            img = document.createElement('img');
            img.className = 'lt-avatar lt-avatar-xl lt-avatar-img';
            img.alt = '';
            var old = picker.querySelector('.lt-avatar');
            picker.replaceChild(img, old);
          }
          img.src = reader.result;
          picker.classList.add('is-uploading');
          form.requestSubmit ? form.requestSubmit() : form.submit();
        };
        reader.readAsDataURL(input.files[0]);
      }, 0);
    });
  });
})();
