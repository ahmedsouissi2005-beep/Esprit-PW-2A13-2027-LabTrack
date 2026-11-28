/**
 * LabTrack - historique interactif des prises (onglet "Historique" des Rappels).
 * Fichier externe : la Content-Security-Policy du site (index.php) bloque les scripts inline.
 *
 *  - un clic sur un jour du calendrier affiche le détail des prises de ce jour ;
 *  - 7 jours / 30 jours pour le graphique d'évolution ;
 *  - un clic sur un médicament filtre le détail sur ce médicament.
 * Les données viennent du bloc <script type="application/json" id="ltHvData">, les textes
 * traduits (fr / ar / en) des attributs data-* de #ltHistory : rien n'est écrit en dur ici.
 * Aucune donnée n'est envoyée : tout est calculé côté serveur, ce script ne fait qu'afficher.
 */
(function () {
  'use strict';

  var root = document.getElementById('ltHistory');
  var dataEl = document.getElementById('ltHvData');
  if (!root || !dataEl) { return; }

  var data;
  try { data = JSON.parse(dataEl.textContent); } catch (err) { return; }
  var daily = data.daily || {};
  var meds = data.meds || [];
  var months = data.months || [];
  var txt = root.dataset;

  var cal = document.getElementById('ltHvCalendar');
  var host = document.getElementById('ltHvDetails');
  var empty = document.getElementById('ltHvNoDetails');
  var filter = document.getElementById('ltHvFilter');
  var selected = txt.today;

  /** Remplace les %d / %s dans l'ordre, comme sprintf côté PHP */
  function fmt(pattern) {
    var args = Array.prototype.slice.call(arguments, 1);
    var i = 0;
    return String(pattern).replace(/%[ds]/g, function () { return String(args[i++]); });
  }

  function label(iso) {
    var p = iso.split('-');
    return parseInt(p[2], 10) + ' ' + (months[parseInt(p[1], 10) - 1] || '') + ' ' + p[0];
  }

  function render(day) {
    selected = day;
    cal.querySelectorAll('button').forEach(function (b) {
      var on = b.dataset.day === day;
      b.classList.toggle('selected', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    var s = daily[day] || { planned: 0, taken: 0, percent: 0 };
    document.getElementById('ltHvDateLabel').textContent = label(day);
    document.getElementById('ltHvDayPercent').textContent = s.percent + '%';
    document.getElementById('ltHvDayCount').textContent = fmt(txt.doses, s.taken, s.planned);
    document.getElementById('ltHvSubtitle').textContent = fmt(txt.detailOf, label(day));

    host.replaceChildren();
    var rows = meds.filter(function (m) {
      return m.daily[day] && m.daily[day].planned > 0 && (!filter || filter.value === 'all' || filter.value === m.id);
    });
    rows.forEach(function (m) {
      var st = m.daily[day];
      var row = document.createElement('article');
      row.className = 'lt-hv-dose-row';
      var title = document.createElement('div');
      title.className = 'lt-hv-dose-title';
      var name = document.createElement('b');
      name.textContent = m.name;                 // textContent : jamais interprété comme du HTML
      var count = document.createElement('small');
      count.textContent = fmt(txt.confirmedOf, st.taken, st.planned);
      title.append(name, count);
      var slots = document.createElement('div');
      slots.className = 'lt-hv-slots';
      (m.plannedHours[day] || []).forEach(function (hour) {
        var ok = (m.takenHours[day] || []).indexOf(hour) !== -1;
        var tag = document.createElement('span');
        tag.className = ok ? 'ok' : 'pending';
        var ico = document.createElement('i');
        ico.className = ok ? 'icofont-check-circled' : 'icofont-clock-time';
        tag.append(ico, document.createTextNode(hour + ' · ' + (ok ? txt.slotOk : txt.slotPending)));
        slots.append(tag);
      });
      row.append(title, slots);
      host.append(row);
    });
    empty.hidden = rows.length > 0;
  }

  cal.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-day]');
    if (b) { render(b.dataset.day); }
  });
  if (filter) { filter.addEventListener('change', function () { render(selected); }); }

  // Graphique : 7 derniers jours par défaut, 30 sur demande
  var bars = Array.prototype.slice.call(document.querySelectorAll('.lt-hv-bar'));
  function showBars(n) {
    bars.forEach(function (b, i) { b.hidden = i < bars.length - n; });
    document.getElementById('ltHvChart').classList.toggle('month', n === 30);
  }
  document.querySelectorAll('.lt-hv-chart-tabs button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.lt-hv-chart-tabs button').forEach(function (x) { x.classList.toggle('active', x === btn); });
      showBars(Number(btn.dataset.days));
    });
  });
  showBars(7);

  // Clic (ou Entrée) sur un médicament : détail filtré sur ce médicament
  document.querySelectorAll('.lt-hv-med').forEach(function (card) {
    function pick() {
      if (filter) { filter.value = card.dataset.med; }
      render(selected);
      document.querySelector('.lt-hv-details').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    card.addEventListener('click', pick);
    card.addEventListener('keydown', function (e) { if (e.key === 'Enter') { pick(); } });
  });

  render(selected);
}());
