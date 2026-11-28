/**
 * LabTrack - formulaire de rappel de médicament : fréquence -> champs d'heure.
 * Fichier séparé : la Content-Security-Policy du site (index.php) interdit les scripts inline.
 *
 * La liste #frequence (1 à 6 prises par jour) affiche autant de champs d'heure
 * (.lt-heure-slot) ; les champs en trop sont masqués et désactivés (donc non envoyés).
 * Le serveur (RappelController::saveReminder) revérifie tout.
 */
(function () {
  'use strict';

  // Heures proposées selon la fréquence (modifiables ensuite)
  var PRESETS = {
    1: ['08:00'],
    2: ['08:00', '20:00'],
    3: ['08:00', '14:00', '20:00'],
    4: ['08:00', '12:00', '16:00', '20:00'],
    5: ['07:00', '10:00', '13:00', '16:00', '20:00'],
    6: ['06:00', '09:00', '12:00', '15:00', '18:00', '21:00']
  };

  var select = document.getElementById('frequence');
  var slots = document.querySelectorAll('.lt-heure-slot');
  if (!select || !slots.length) { return; }

  // En modification, on garde les heures enregistrées ; en ajout, on propose les heures types
  var touched = select.getAttribute('data-editing') === '1';

  function update() {
    var n = Math.min(6, Math.max(1, parseInt(select.value, 10) || 1));
    for (var i = 0; i < slots.length; i++) {
      var slot = slots[i];
      var input = slot.querySelector('input');
      var on = i < n;
      slot.hidden = !on;
      slot.style.display = on ? '' : 'none';
      input.disabled = !on;
      if (on) { input.setAttribute('required', 'required'); } else { input.removeAttribute('required'); }
      input.classList.remove('is-invalid');
      // Champ vide, ou heures jamais modifiées à la main : on propose les heures types
      if (on && (input.value === '' || !touched)) { input.value = PRESETS[n][i]; }
    }
  }

  select.addEventListener('change', update);
  for (var k = 0; k < slots.length; k++) {
    slots[k].querySelector('input').addEventListener('input', function () { touched = true; });
  }
  // Au retour sur la page (le navigateur peut restaurer l'ancienne valeur de la liste)
  window.addEventListener('pageshow', update);
  update();
})();
