/**
 * LabTrack - rappel vocal (onglet "Aujourd'hui" des Rappels)
 *
 * Lit les prises à voix haute avec la Web Speech API du navigateur (speechSynthesis) :
 * aucun service externe, aucune donnée envoyée, la voix est celle installée sur l'appareil.
 *  - un bouton haut-parleur sur chaque prise
 *  - "Écouter les prises" : résumé puis prises restantes (un second clic arrête la lecture)
 *  - "Rappel vocal automatique" : lit la prise à son heure tant que la page reste ouverte
 *
 * Fichier externe obligatoire (CSP : aucun script inline). Tous les textes (fr / ar / en)
 * viennent du serveur par des attributs data-* : rien n'est écrit en dur ici.
 */
(function () {
  'use strict';

  var bar = document.getElementById('ltVoice');
  if (!bar) { return; }

  var controls = document.getElementById('ltVoiceControls');
  var statusEl = document.getElementById('ltVoiceStatus');
  var readAll = document.getElementById('ltVoiceReadAll');
  var autoBox = document.getElementById('ltVoiceAuto');
  var d = bar.dataset;
  var synth = window.speechSynthesis;

  bar.hidden = false;
  if (!synth || typeof window.SpeechSynthesisUtterance === 'undefined') {
    controls.hidden = true;
    setStatus(d.unsupported);
    return;
  }

  var LOCALES = { fr: 'fr-FR', ar: 'ar-SA', en: 'en-US' };
  var htmlLang = (document.documentElement.lang || 'fr').slice(0, 2).toLowerCase();
  var locale = LOCALES[htmlLang] || 'fr-FR';
  var STORE_AUTO = 'lt_voice_auto';
  var STORE_SPOKEN = 'lt_voice_spoken';
  var WINDOW_MIN = 5;      // une prise est lue si son heure date de moins de 5 min (minuteurs ralentis en arrière-plan)

  var rows = Array.prototype.slice.call(document.querySelectorAll('.lt-dose[data-speak]'));
  var voice = null;
  var voicesKnown = false;
  var armed = false;       // le navigateur n'autorise la voix qu'après un geste de l'utilisateur
  var pending = [];        // phrases en attente de ce geste
  var speaking = false;
  var run = 0;             // numéro de lecture : ignore les évènements d'une lecture annulée
  var keep = [];           // phrases en cours de lecture (voir say)

  function setStatus(msg) {
    if (!statusEl) { return; }
    statusEl.textContent = msg || '';
    statusEl.hidden = !msg;
  }

  function store(action, key, value) {
    try {
      if (action === 'set') { window.localStorage.setItem(key, value); }
      return window.localStorage.getItem(key);
    } catch (err) { return null; }
  }

  function loadSpoken() {
    try { return JSON.parse(window.sessionStorage.getItem(STORE_SPOKEN) || '[]'); } catch (err) { return []; }
  }
  function saveSpoken(list) {
    try { window.sessionStorage.setItem(STORE_SPOKEN, JSON.stringify(list.slice(-200))); } catch (err) { /* ignoré */ }
  }

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  /** Meilleure voix pour la langue de la page (arabe : ar-TN > ar-SA > autre arabe) */
  function pickVoice() {
    var list = synth.getVoices() || [];
    var prefix = locale.slice(0, 2);
    var best = null;
    var rank = -1;
    list.forEach(function (v) {
      var l = (v.lang || '').replace('_', '-').toLowerCase();
      if (l.indexOf(prefix) !== 0) { return; }
      var score = 1;
      if (l === locale.toLowerCase()) { score = 3; }
      if (prefix === 'ar' && l === 'ar-tn') { score = 4; }
      if (v.localService) { score += 0.5; }
      if (score > rank) { best = v; rank = score; }
    });
    voice = best;
    voicesKnown = list.length > 0;
  }

  function autoOn() { return !!(autoBox && autoBox.checked); }

  function refreshStatus() {
    if (autoOn() && !armed) { setStatus(d.tap); }
    else if (voicesKnown && !voice) { setStatus(d.noVoice); }
    else { setStatus(''); }
  }

  function setSpeaking(on) {
    speaking = on;
    if (readAll) {
      readAll.classList.toggle('is-speaking', on);
      var label = readAll.querySelector('span');
      if (label) { label.textContent = on ? d.stop : d.readAll; }
    }
  }

  /** Lit une ou plusieurs phrases à la suite (remplace la lecture en cours) */
  function say(texts) {
    texts = texts.filter(function (t) { return t && t.trim() !== ''; });
    if (!texts.length) { return; }
    if (!armed) {
      pending = pending.concat(texts);
      refreshStatus();
      return;
    }
    var mine = ++run;
    synth.cancel();
    // Les phrases sont gardées dans "keep" : Chrome supprime sinon les utterances qu'aucune variable
    // ne référence plus, et la lecture s'arrête au milieu ou ne démarre pas.
    keep = texts.map(function (text, i) {
      var u = new window.SpeechSynthesisUtterance(text);
      u.lang = voice ? voice.lang : locale;
      if (voice) { u.voice = voice; }
      u.rate = 0.9;      // un peu plus lent : plus facile à suivre
      if (i === texts.length - 1) {
        u.onend = u.onerror = function () { if (mine === run) { setSpeaking(false); } };
      }
      return u;
    });
    var list = keep;
    setSpeaking(true);
    // Chrome perd souvent un speak() lancé juste après cancel() : on laisse passer un court instant.
    setTimeout(function () {
      if (mine !== run) { return; }   // lecture remplacée ou arrêtée entre-temps
      list.forEach(function (u) { synth.speak(u); });
    }, 80);
  }

  /** Premier geste de l'utilisateur : la voix est autorisée, on lit ce qui attendait */
  function arm() {
    if (armed) { return; }
    armed = true;
    // Safari (iPhone) n'autorise ensuite les lectures lancées par le minuteur qu'après un premier speak()
    // déclenché par un geste de l'utilisateur : une phrase vide, sans son, suffit à le débloquer.
    try {
      var unlock = new window.SpeechSynthesisUtterance('');
      unlock.volume = 0;
      synth.speak(unlock);
    } catch (err) { /* ignoré */ }
    var waiting = pending;
    pending = [];
    if (waiting.length) { say(waiting); }
    refreshStatus();
  }
  ['pointerdown', 'keydown', 'touchstart'].forEach(function (evt) {
    document.addEventListener(evt, arm, { capture: true, passive: true });
  });

  function remainingRows() {
    return rows.filter(function (r) { return r.dataset.state !== 'pris' && r.dataset.state !== 'oublie'; });
  }

  function ring(row) {
    row.classList.add('lt-dose-ringing');
    setTimeout(function () { row.classList.remove('lt-dose-ringing'); }, 60000);
  }

  /** Rappel automatique : lit chaque prise non confirmée à son heure (une seule fois par jour) */
  function tick() {
    if (!autoOn()) { return; }
    var now = new Date();
    var nowMin = now.getHours() * 60 + now.getMinutes();
    var day = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    var spoken = loadSpoken();
    var texts = [];
    remainingRows().forEach(function (r) {
      var p = (r.dataset.time || '').split(':');
      var doseMin = parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
      var key = day + '|' + r.dataset.key;
      var late = nowMin - doseMin;
      if (!isNaN(doseMin) && late >= 0 && late <= WINDOW_MIN && spoken.indexOf(key) === -1) {
        spoken.push(key);
        texts.push(r.dataset.speak);
        ring(r);
      }
    });
    if (texts.length) {
      saveSpoken(spoken);
      say(texts);
    }
  }

  // ---- branchement ----
  pickVoice();
  if (synth.addEventListener) {
    synth.addEventListener('voiceschanged', function () { pickVoice(); refreshStatus(); });
  }

  document.querySelectorAll('.lt-speak').forEach(function (btn) {
    btn.hidden = false;
    btn.addEventListener('click', function () {
      var row = btn.closest('.lt-dose');
      arm();
      if (row) { say([row.dataset.speak]); }
    });
  });

  if (readAll) {
    readAll.addEventListener('click', function () {
      arm();
      if (speaking) {
        run++;
        synth.cancel();
        setSpeaking(false);
        return;
      }
      var todo = remainingRows();
      if (!todo.length) { say([d.allDone]); return; }
      var texts = [d.left];
      todo.forEach(function (r) {
        texts.push(d.at.replace('%s', r.dataset.time) + ' ' + r.dataset.speak);
      });
      say(texts);
    });
  }

  if (autoBox) {
    autoBox.checked = store('get', STORE_AUTO) === '1';
    autoBox.addEventListener('change', function () {
      store('set', STORE_AUTO, autoBox.checked ? '1' : '0');
      if (autoBox.checked) {
        arm();
        say([d.enabled]);
        tick();
      }
      refreshStatus();
    });
  }

  refreshStatus();
  tick();
  setInterval(tick, 15000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { tick(); } });
  window.addEventListener('pagehide', function () { synth.cancel(); });
}());
