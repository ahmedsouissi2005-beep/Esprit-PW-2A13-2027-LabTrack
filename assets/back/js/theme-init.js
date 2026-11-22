/* LabTrack Admin - appliqué dans le <head>, avant l'affichage, pour éviter un flash :
   thème clair/sombre mémorisé et état réduit du menu latéral (repris du template Registrar). */
document.documentElement.classList.add('js');
try {
  var theme = localStorage.getItem('labtrack-theme');
  if (theme !== 'light' && theme !== 'dark') {
    theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-bs-theme', theme);
  if (localStorage.getItem('labtrack-sidebar') === 'collapsed') {
    document.documentElement.classList.add('sidebar-collapsed');
  }
} catch (e) { /* stockage indisponible */ }
