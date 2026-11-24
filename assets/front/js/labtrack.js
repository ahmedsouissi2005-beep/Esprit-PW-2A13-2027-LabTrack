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

  // Mot de passe oublié : compte à rebours avant l'expiration du code (5 minutes).
  // Le serveur refuse de toute façon un code expiré ; ceci n'est qu'un affichage.
  document.querySelectorAll('[data-countdown]').forEach(function (box) {
    var end = Date.now() + parseInt(box.getAttribute('data-countdown'), 10) * 1000;
    var value = box.querySelector('.lt-countdown-value');
    function tick() {
      var left = Math.max(0, Math.round((end - Date.now()) / 1000));
      value.textContent = Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2);
      box.classList.toggle('is-warning', left > 0 && left <= 60);
      if (left === 0) {
        box.classList.add('is-over');
        clearInterval(timer);
      }
    }
    var timer = setInterval(tick, 1000);
    tick();
  });
})();
