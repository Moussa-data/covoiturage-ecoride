/* ============================================================
   EcoRide — formulaire "Publier un trajet"
   Trajet régulier : affiche les options et résume le nombre
   de trajets qui seront créés ("8 trajets : du lun. 24 nov. au …").
   ============================================================ */

(function () {
  const caseRegulier = document.getElementById('regulier');
  const options      = document.getElementById('regulierOptions');
  const resume       = document.getElementById('regulierResume');
  const champDate    = document.getElementById('date');
  const semaines     = document.getElementById('semaines');
  if (!caseRegulier || !options) return;

  const JOURS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
  const MOIS  = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  const court = (d) => JOURS[d.getDay()] + ' ' + d.getDate() + ' ' + MOIS[d.getMonth()];

  function calculer() {
    if (!caseRegulier.checked) return;
    const jours = [...document.querySelectorAll('input[name="jours[]"]:checked')].map((c) => Number(c.value));
    if (!champDate.value) {
      resume.textContent = 'Choisissez d\'abord la date du premier trajet.';
      return;
    }
    if (jours.length === 0) {
      resume.textContent = 'Cochez au moins un jour.';
      return;
    }
    // Même calcul que datesTrajetRegulier() en PHP (1 = lundi … 7 = dimanche)
    const debut = new Date(champDate.value + 'T12:00:00');
    const dates = [];
    for (let i = 0; i < Number(semaines.value) * 7; i++) {
      const d = new Date(debut);
      d.setDate(debut.getDate() + i);
      const n = d.getDay() === 0 ? 7 : d.getDay();
      if (jours.includes(n)) dates.push(d);
    }
    resume.textContent = dates.length === 0
      ? 'Aucune date ne correspond.'
      : dates.length + ' trajet' + (dates.length > 1 ? 's' : '') + ' seront publiés : du ' + court(dates[0]) + ' au ' + court(dates[dates.length - 1]) + '.';
  }

  caseRegulier.addEventListener('change', () => {
    options.hidden = !caseRegulier.checked;
    calculer();
  });
  options.addEventListener('change', calculer);
  champDate.addEventListener('change', calculer);
  calculer();
})();

/* ---------- Prix maximum conseillé (partage de frais) ----------
   Même règle que le serveur (prixMaxParPassager en PHP) :
   distance × coût d'un km ÷ (places + 1), avec un minimum de 2 €. */
(function () {
  var plafond = document.getElementById('plafondPrix');
  var distance = document.getElementById('distance_km');
  var places = document.getElementById('places_dispo');
  var prix = document.getElementById('prix');
  if (!plafond || !distance || !places || !prix) return;

  var coutKm = parseFloat(plafond.dataset.coutKm);
  var plancher = parseFloat(plafond.dataset.plancher);
  var texteDefaut = plafond.textContent;

  function euros(n) { return n.toFixed(2).replace('.', ',') + ' €'; }

  function maj() {
    var d = parseInt(distance.value, 10);
    var p = parseInt(places.value, 10) || 1;
    if (!d || d < 1) { plafond.textContent = texteDefaut; plafond.classList.remove('plafond-depasse'); return; }
    var max = Math.max(d * coutKm / (p + 1), plancher);
    max = Math.round(max * 100) / 100;
    var valeur = parseFloat(String(prix.value).replace(',', '.'));
    var depasse = valeur > max;
    plafond.classList.toggle('plafond-depasse', depasse);
    plafond.textContent = (depasse ? 'Trop cher : ' : '') + 'pour ' + d + ' km et ' + p + ' place' + (p > 1 ? 's' : '')
      + ', le prix maximum est de ' + euros(max) + ' par passager.';
  }
  [distance, places, prix].forEach(function (el) { el.addEventListener('input', maj); });
  maj();
})();
